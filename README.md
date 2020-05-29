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

## More info

The REST API provided by the ARCHE is quite a low-level from the point of view of real-world data ingestions.
To make ingestions simpler, the [arche-lib-ingest](https://github.com/acdh-oeaw/arche-lib-ingest) library has been developed.
While it provides a convenient high-level data ingestion API, it's still only a library which requires you to write your own ingestion script.

This repository is aimed at closing this gap - it provides a set of sample data ingestion scripts (using the arche-lib-ingest) which can be used by people with almost no programming skills.

