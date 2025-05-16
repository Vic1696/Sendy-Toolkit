require('dotenv').config();
const express = require('express');
const multer = require('multer');
const { default: fetch } = require('node-fetch');
const path = require('path');

const app = express();
const port = 3000;

const storage = multer.memoryStorage();
const upload = multer({ storage: storage });

async function subscribeUser(email, name = '') {
    const apiKey = process.env.SENDY_API_KEY;
    const listId = process.env.SENDY_LIST_ID;
    const subscribeUrl = process.env.SENDY_API_URL;

    if (!apiKey || !listId || !subscribeUrl) {
        console.error('Error: Please ensure SENDY_API_KEY, SENDY_LIST_ID, and SENDY_API_URL are set in your .env file.');
        return { status: 'error', message: 'Server configuration error.' };
    }

    const params = new URLSearchParams();
    params.append('api_key', apiKey);
    params.append('list', listId);
    params.append('email', email.trim()); // Ensure email is trimmed
    if (name) {
        params.append('name', encodeURIComponent(name.trim())); // Encode trimmed name
    }

    try {
        const response = await fetch(subscribeUrl, {
            method: 'POST',
            body: params,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            redirect: 'manual',
        });

        const responseText = await response.text();
        console.log(`Sendy Response for ${email} (name: "${name}"):`, responseText);

        if (responseText.includes("<title>You're subscribed!</title>")) {
            return { status: 'subscribed', message: `Successfully subscribed ${email} ${name ? `(${name})` : ''}` };
        } else if (responseText.includes("<title>You're already subscribed!</title>")) {
            return { status: 'already_subscribed', message: `${email} is already subscribed.` };
        } else if (responseText.includes("<title>Email address is bounced.</title>")) {
            return { status: 'bounced', message: `Failed to subscribe ${email} - email address is bounced.` };
        } else if (responseText.includes("<title>Email address is invalid.</title>")) {
            return { status: 'invalid', message: `Failed to subscribe ${email} - email address is invalid.` };
        } else {
            console.error(`Unexpected Sendy Response for ${email} (Status ${response.status}):`, responseText);
            return { status: 'unexpected', message: `Failed to subscribe ${email}. Unexpected response from Sendy: ${responseText.substring(0, 200)}...` };
        }
    } catch (error) {
        console.error('An error occurred during the API request:', error);
        return { status: 'error', message: `API request error: ${error.message}` };
    }
}

app.post('/upload', upload.single('csvFile'), async (req, res) => {
    if (!req.file) {
        return res.status(400).send('No file uploaded.');
    }

    const fileBuffer = req.file.buffer.toString('utf8');
    const lines = fileBuffer.trim().split('\n');
    let subscribedCount = 0;
    let bouncedCount = 0;
    let alreadySubscribedCount = 0;
    let invalidCount = 0;
    let unexpectedErrors = [];

    for (const line of lines) {
        const columns = line.trim().split(',');
        let email = null;
        let name = null;

        // Iterate through the columns to find the email address
        for (const col of columns) {
            if (col.includes('@') && col.includes('.')) {
                email = col.trim();
                break; 
            }
        }

        // If an email is found, try to extract the name (assuming the other column is the name)
        if (email) {
            for (const col of columns) {
                if (col.trim() !== email) {
                    name = col.trim();
                    break; 
                }
            }

            const subscriptionResult = await subscribeUser(email, name);
            switch (subscriptionResult.status) {
                case 'subscribed':
                    subscribedCount++;
                    break;
                case 'already_subscribed':
                    alreadySubscribedCount++;
                    break;
                case 'bounced':
                    bouncedCount++;
                    break;
                case 'invalid':
                    invalidCount++;
                    break;
                case 'unexpected':
                    unexpectedErrors.push(subscriptionResult.message);
                    break;
                case 'error':
                    unexpectedErrors.push(subscriptionResult.message);
                    break;
            }
        } else {
            invalidCount++; 
        }
    }

    res.json({
        subscribed: subscribedCount,
        bounced: bouncedCount,
        alreadySubscribed: alreadySubscribedCount,
        invalid: invalidCount,
        errors: unexpectedErrors
    });
});

app.use(express.static(path.join(__dirname)));

app.listen(port, () => {
    console.log(`Server listening at http://localhost:${port}`);
});

app.post('/upload', upload.single('csvFile'), async (req, res) => {
    if (!req.file) {
        return res.status(400).send('No file uploaded.');
    }

    const fileBuffer = req.file.buffer.toString('utf8');
    const lines = fileBuffer.trim().split('\n');
    let subscribedCount = 0;
    let bouncedCount = 0;
    let alreadySubscribedCount = 0;
    let invalidCount = 0;
    const errors = [];
    const failedEmails = {
        bounced: [],
        invalid: []
    };

    for (const line of lines) {
        const columns = line.trim().split(',');
        let email = null;
        let name = null;

        for (const col of columns) {
            if (col.includes('@') && col.includes('.')) {
                email = col.trim();
                break;
            }
        }

        if (email) {
            for (const col of columns) {
                if (col.trim() !== email) {
                    name = col.trim();
                    break;
                }
            }

            const subscriptionResult = await subscribeUser(email, name);
            switch (subscriptionResult.status) {
                case 'subscribed':
                    subscribedCount++;
                    break;
                case 'already_subscribed':
                    alreadySubscribedCount++;
                    break;
                case 'bounced':
                    bouncedCount++;
                    failedEmails.bounced.push(email);
                    break;
                case 'invalid':
                    invalidCount++;
                    failedEmails.invalid.push(email);
                    break;
                case 'unexpected':
                    errors.push(subscriptionResult.message);
                    break;
                case 'error':
                    errors.push(subscriptionResult.message);
                    break;
            }
        } else {
            invalidCount++;
        }
    }

    res.json({
        subscribed: subscribedCount,
        bounced: bouncedCount,
        alreadySubscribed: alreadySubscribedCount,
        invalid: invalidCount,
        errors: errors,
        failedEmails: failedEmails
    });
});

// Serve the frontend
app.use(express.static(path.join(__dirname)));

app.listen(port, () => {
    console.log(`Server listening at http://localhost:${port}`);
});