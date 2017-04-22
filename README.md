# modem-watcher
PHP script that watches for signal health and general operation of your Motorola Surfboard. Details are logged to SYSLOG for record-keeping.

## Setup and Usage

1. **Verify that PHP CLI in installed**
    + Execute ```php -v``` and see that PHP v5 or greater is installed
1. **Update Composer** ( _[More Details @ https://getcomposer.org/](https://getcomposer.org/doc/00-intro.md)_ )
    + In the project directory, execute: ```composer install```
2. **Set the Cron**
    + Execute ```crontab -e``` 
    + Add ```0       *       *       *       *       php /{your path}/modem-watcher/getSbDetails.php``` to the end
3. **Execute the Script Directly**
    + Execute ```php /{your path}/modem-watcher/getSbDetails.php``` 

## Details

This script loads the web interface for you Motorola surfboard and parses the page. It grabs details related to the status of the device and its signal strength. All the details are written to SYSLOG with the application stamp of "surf-log" though in writing this I see that it should probably point to modem-watcher...