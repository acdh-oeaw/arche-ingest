# A collection of ARCHE ingestion script templates

## Usage

* Clone this repository.
* Run `composer update` in the repository root directory.
* Fetch the ARCHE instance configuration by downloading `{ARCHE instance base URL}/desribe` (e.g. https://arche-new.acdh-dev.oeaw.ac.at/api/describe). Save the downloaded file as `config.yaml`.
* Extend the `config.yaml` with the content of `config-additions.yaml` (if you ).
    * Don't forget to provide the client authorization data.
      If you want to create yourself a user in the ARCHE instance please take a look at https://github.com/acdh-oeaw/arche-docker-config/blob/arche/initScripts/users.yaml.sample
* Open and adjust the top section of a `*_sample.php` file of your choice:
    * set `$configLocation = './config.yaml';` and `$composerLocation  = './';`
    * you can also set `$runComposerUpdate = false;` (as you have just did it)
    * adjust other options according to your preferences
* Run the file, e.g. `php -f import_metadata_sample.php`.

## Instructions for the arche-ingestion@herkules.acdh.oeaw.ac.at

Skip the instructions above.

Copy a current template from this directory into your collection import scripts directory.
It will assure your ingestion script will be correct and up to date.

Then adjust the settings at the top of a file (leave `$configLocation` and `$composerLocation` as they are) and run the file.

