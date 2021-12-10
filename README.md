# Background

PHP file processor to calculate totals and profit margins and perform live currency conversion against an API.

This script assumes:
1. The input CSV will always have at least a header row with the relevant columns (sku, cost, price, qty)
2. The data in the cost, price, and qty colums will be numeric

This script can accomodate:
1. Any order of columns
2. Various irrelevant columns
3. Null values in input (they will not be factored into the averages or converted to $0.00)
4. Negative values in input

This script will:
1. Produce an html table outputting the formatted file contents, the profit per product, and the total profit per product.
2. Produce a footer summarizing information of average cost, average price, total qty, average profit margin, and total profit.
3. Display negative values in red and positive values in green.
4. Display the Total Profit for each row and the entire table in CAD (Canadian dollars) using a real-time look-up via  https://exchangeratesapi.io/.
5. Format all dollar values (e.g. $4.55).

# Requirements

1. This script must be run from a server supporting PHP and Curl. MAMP or WAMP are good localhost solutions.
2. Directory structure must be preserved, i.e. there must be a folder named 'uploads' in the same directory as the main script.

# Instructions

1. Download files to server public directory.
2. Open `sales-summary.php` in a browser window.
3. Upload a csv-formatted file.


## Data files

### `sample-data_1.csv`

A clean list with only the correct column headers in the correct order.

### `sample-data_2.csv`

Contains extra column headers, columns out of order, negative values.

### `sample-data_3.csv`

Contains only the header row.

### `sample-data_4.csv`

Contains null values.


# Notes

- I provided various stringent validations for file uploads.

- I used an Object Oriented Programming (OOP) approach to the problem.

- I provided various error catches and messages throughout the program to help the user with troubleshooting.
