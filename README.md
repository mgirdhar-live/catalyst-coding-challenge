sci
Command line script to import users into postgress database.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

## Note
```
CD to the project directory to run any docker-compose command.
```
## Installation

* Install [Docker](https://docs.docker.com/get-started/)
* Build: `docker-compose build`
* Run: `docker-compose up`

## Commands to run application
* Run: `docker-compose exec custom-backend php user_upload.php -u=customuser -p=custompassword -h=custom-db --create_table`
* Run: `docker-compose exec custom-backend php user_upload.php -u=customuser -p=custompassword -h=custom-db --file=users.csv`
* Run: `docker-compose exec custom-backend php user_upload.php -u=customuser -p=custompassword -h=custom-db --file=users.csv --dry_run`
```
Csv file should be placed in backend folder for instance backend/users.csv
```
* Run: `docker-compose exec custom-backend php user_upload.php -u=customuser -p=custompassword -h=custom-db --help`

### Database Information

#### User Table

- Table Name: `User`
- Serial Key: `id (Unsigned Integer)`

## Sample data & Testing
* For testing, I have included the sample data here in this file: backend/users.csv. Please feel free to add more rows/data here and test.


## Resources

* [Docker](https://www.docker.com/)
