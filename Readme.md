# Bulk Subscriber Tool

This is a simple web application that allows you to upload a CSV file containing a list of email addresses (and optionally names) to subscribe them in bulk to a Sendy mailing list. It provides feedback on the number of successfully subscribed, already subscribed, bounced, and invalid email addresses. It also lists the specific email addresses that bounced or were considered invalid.

## Prerequisites

Before you can run this application, you need to have the following installed:

* **Node.js:** Ensure you have Node.js and npm (Node Package Manager) installed on your system. You can download them from [https://nodejs.org/](https://nodejs.org/).

## Setup

1.  **Clone the Repository (if applicable):** If you have the code in a Git repository, clone it to your local machine.

    ```bash
    git clone <repository_url>
    cd <repository_directory>
    ```

2.  **Install Dependencies:** Navigate to the project directory in your terminal and install the necessary Node.js packages using npm.

    ```bash
    npm install
    ```

    This command will install `express`, `multer`, and `node-fetch`.

3.  **Configure Environment Variables:** Create a `.env` file in the root of your project directory and add the following environment variables with your Sendy API credentials and list ID:

    ```env
    SENDY_API_KEY=YOUR_SENDY_API_KEY
    SENDY_LIST_ID=YOUR_SENDY_LIST_ID
    SENDY_API_URL=YOUR_SENDY_API_URL/subscribe
    ```

    * `YOUR_SENDY_API_KEY`: Your Sendy API key. You can find this in your Sendy settings.
    * `YOUR_SENDY_LIST_ID`: The ID of the Sendy list you want to subscribe users to. You can find this in your Sendy list settings.
    * `YOUR_SENDY_API_URL`: The URL to your Sendy installation's subscribe API endpoint. Make sure to append `/subscribe` to your Sendy base URL. For example, `https://your-sendy-domain.com/subscribe`.

## Running the Application

1.  **Start the Server:** In your terminal, within the project directory, run the following command to start the Node.js server:

    ```bash
    node server.js
    ```

    This will start the server on `http://localhost:3000`.

2.  **Open in Browser:** Open your web browser and navigate to `http://localhost:3000`. You should see the "Bulk Subscriber" web interface.

## Usage

1.  **Choose CSV File:** Click the "Choose File" button to select the CSV file from your local machine that contains the list of email addresses (and optional names) you want to subscribe.

    * **CSV Format:** The tool expects a plain text CSV file where each line represents one contact. The email address should be present in at least one of the columns. If you want to include a name, it should be in a different column on the same line. The order of columns does not matter; the script will search for an email address in each line.

2.  **Upload CSV:** After selecting the file, click the "Upload CSV" button.

3.  **Processing and Status:** The application will display a "Processing file..." message while it uploads and processes the data. Once the processing is complete, the status area below the button will be updated with the following information:

    * Number of successfully subscribed users.
    * Number of email addresses that were already subscribed to the list.
    * Number of bounced email addresses (as reported by Sendy).
    * Number of invalid email addresses (either due to format or reported as invalid by Sendy).
    * A list of the specific email addresses that bounced.
    * A list of the specific email addresses that were considered invalid.
    * Any unexpected errors encountered during the subscription process.

## Code Structure

* `server.js`: Contains the Node.js server-side logic using Express to handle file uploads and interact with the Sendy API.
* `index.html`: The front-end HTML file providing the user interface for file selection and displaying the status.
* `package.json`: Defines the project dependencies and scripts.
* `.env`: Stores sensitive configuration information like API keys and URLs (should not be committed to version control).

## Future Enhancements

As outlined previously, here are some potential features that could be added in the future:

* More detailed error messages from Sendy.
* A progress indicator for large files.
* Clearer real-time status updates.
* Support for different CSV delimiters.
* More flexible CSV parsing options (specifying email and name columns).
* Ignoring empty lines or header rows in the CSV.
* Bulk unsubscribe functionality.
* Adding custom fields to subscribers.
* Selecting the Sendy list via the UI.
* Saving upload history.
* User authentication.
* Integration with email verification services.

## Contributing

If you would like to contribute to this project, please feel free to fork the repository and submit pull requests with your enhancements or bug fixes.
