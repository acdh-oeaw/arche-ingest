# A collection of ARCHE ingestion script templates

## Usage

* Clone this repository.
* Run `composer update` in the repository root directory.
* Fetch the ARCHE instance configuration by downloading `{ARCHE instance base URL}/desribe` (e.g. https://arche-new.acdh-dev.oeaw.ac.at/api/describe) and save it as `config.yaml`.
* Open and adjust the top section of a `*_sample.php` file of your choice:
    * set `$configLocation = './config.yaml';` and `$composerLocation  = './';`
    * you can also set `$runComposerUpdate = false;` (as you have just did it)
    * adjust other options according to your preferences
* Run the file, e.g. `php -f import_metadata_sample.php`.
    * Every script will ask you for credentials - you should get them from the ARCHE instance admin.:w
    * If you need to create yourself a user account please take a look at https://github.com/acdh-oeaw/arche-docker-config/blob/arche/initScripts/users.yaml.sample

## Instructions for the arche-ingestion@herkules.acdh.oeaw.ac.at

Skip the instructions above.

Copy a current template from this directory into your collection import scripts directory.
It will assure your ingestion script will be correct and up to date.

Then adjust the settings at the top of a file (leave `$configLocation` and `$composerLocation` as they are) and run the file.

### Reporting errors

* Create a subtask of the Redmine issue [#17641](https://redmine.acdh.oeaw.ac.at/issues/17641).
    * Provide information on the exact location of the ingestion script location (including the script file itself) and any other information which may be required to replicated the problem.
    * Assign Mateusz and Norbert as watchers.

### Running long tasks

* Prepare a file with input data.  
  In points below it's assumed this file name is `input` and it's stored in the same directory as the import script.  
  The file should contain of four lines:
  ```
  {instanceNumber}
  yes
  {yourLogin}
  {yourPassword}
  ```
  where `{instanceNumber}` is `1` for the development instance, `2` for the production instance and `3` for the curation instance, e.g.
  ```
  2
  yes
  pandorfer
  veryStrongPassword
  ```
* Use `screen` so you can leave the script running even if the connection to the server is lost or when you turn your computer off.  
  After logging into arche-ingestion@herkules.acdh.oeaw.ac.at run:
  ```bash
  screen -S yourSessionName ~/login.sh
  ```
* Run the script redirecting output to a log file (to assure whole output is preserved), e.g.:
  ```bash
  php -f import_metadata_sample.php < input > log_file 2>&1
  ```
* Leave the `screen` session by hitting `CTRL+a` followed by `d`.  
  You are now in the host shell and you can track the script execution progress with `tail -f log_file` (in the script's directory).
* To go back to the shell where you script is running run `screen -r yourSessionName`.

## More info

The REST API provided by the ARCHE is quite a low-level from the point of view of real-world data ingestions.
To make ingestions simpler, the [arche-lib-ingest](https://github.com/acdh-oeaw/arche-lib-ingest) library has been developed.
While it provides a convenient high-level data ingestion API, it's still only a library which requires you to write your own ingestion script.

This repository is aimed at closing this gap - it provides a set of sample data ingestion scripts (using the arche-lib-ingest) which can be used by people with almost no programming skills.

