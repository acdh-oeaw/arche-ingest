# A collection of ARCHE ingestion script templates

The REST API provided by the ARCHE is quite a low-level from the point of view of real-world data ingestions.
To make ingestions simpler, the [arche-lib-ingest](https://github.com/acdh-oeaw/arche-lib-ingest) library has been developed.
While it provides a convenient high-level data ingestion API, it's still only a library which requires you to write your own ingestion script.

This repository is aimed at closing this gap - it provides a set of data ingestion scripts (built on top of the [the arche-lib-ingest](https://github.com/acdh-oeaw/arche-lib-ingest))
which can be used by people with almost no programming skills.

## Scripts provided

There are two script variants provided:

* **Console scripts variant** where where parameters are passed trough the command line.  
  The benefit of this variant is easiness of use, especially in CI/CD workflows.
  * `bin/arche-import-metadata` imports metadata from an RDF file
  * `bin/arche-import-binary` (re)ingests a single resource's binary content (to be used when file name and/or location changed)
  * `bin/arche-delete-resource` removes a given repository resource (allows recursion, etc.)
  * `bin/arche-delete-triples` removes metadata triples specified in the ttl file (but doesn't remove repository resources)
  * `bin/arche-update-redmine` updates a Redmine issue describing the data curation/ingestion process
    (see a dedicated section at the bottom of the README)
* **Template variant** where you adjust execution parameters and/or the way the script works by editign its content.  
  The benefit of this variant is that it allows to treat the adjusted script as a documentation of the ingestion process and/or adjust it to your particular needs.
  * `add_metadata_sample.php` adds metadata triples specified in the ttl file preserving all existing metadata of repository resources
  * `delete_metadata_sample.php` removes metadata triples specified in the ttl file (but doesn't remove repository resources)
  * `delete_resource_sample.php` removes a given repository resource (allows recursion, etc.)
  * `import_binary_sample.php` imports binary data from the disk
  * `import_metadata_sample.php` imports metadata from an RDF file
  * `reimport_single_binary.php` reingests a single resource's binary content (to be used when file name and/or location changed)

## Installation & Usage

### Runtime environment

You need [PHP](https://www.php.net/) and [Composer](https://getcomposer.org/).

You can also use the `acdhch/arche-filechecker` [Docker](https://www.docker.com/) image
(the `{pathToDirectoryWithFilesToIngest}` will be available at the `/data` location inside the Docker container):

```bash
docker run \
  --rm \
  -ti \
  --name arche-ingest \
  --entrypoint bash \
  -v {pathToDirectoryWithFilesToIngest}:/data \
  acdhch/arche-filechecker
```

### Console script variant

* Install with:
  ```bash
  composer require acdh-oeaw/arche-ingest
  ```
* Update regularly with:
  ```
  composer update --no-dev
  ```
* Run with:
  ```bash
  vendor/bin/{scriptOfYourChoice} {parametersGoHere}
  ```
  e.g.
  ```bash
  vendor/bin/arche-import-metadata --concurrency 4 myRdf.ttl https://arche.acdh.oeaw.ac.at/api myLogin myPassword
  ```
  * To get the list of available parameters run
    ```bash
    vendor/bin/{scriptOfYourChoice} --help
    ```
    e.g.
    ```bash
    vendor/bin/arche-import-metadata --help
    ```

#### Running inside GitHub Actions

Do not store your ARCHE credentials in the workflow configuration file. Use repository secrets instead (see example below).

A fragment of your workflow's yaml config may look like that:

```yaml
    - name: ingestion  dependencies
      run: |
        composer require acdh-oeaw/arche-ingest
    - name: ingest arche
      run: |
        vendor/bin/arche-import-metadata myRdfFile.ttl https://arche-curation.acdh-dev.oeaw.ac.at/api ${{secrets.ARCHE_LOGIN}} ${{secrets.ARCHE_PASSWORD}}
        vendor/bin/arche-update-redmine --token ${{ secrets.REDMINE_TOKEN }} https://redmine.acdh.oeaw.ac.at 1234 'Upload AIP to Curation Instance (Minerva)'
```

#### Running on repo-ingestion@hephaistos

* Log into `repo-ingestion@hephaistos`
* Run `screen -S mySessionName ./login.sh`
* Go to your ingestion directory
* Run scripts using `/ARCHE/vendor/bin/{script}` (instead of `vendor/bin/{script}`), e.g.
  ```bash
  /ARCHE/vendor/bin/arche-import-metadata --concurrency 4 myRdf.ttl https://arche.acdh.oeaw.ac.at/api myLogin myPassword
  ```
* If the script will take long to run, you may safely quit the console with `CTRL+D` followed by `exit`.
  * To get back to the script log again into `repo-ingestion@hephaistos` and run
    ```bash
    screen -r mySessionName
    ```

### Template variant

* Clone this repository.
* Run
  ```bash
  composer update --no-dev
  ```
* Adjust the script of your choice.
  * Available parameters are provided at the beginning of the script.
  * Don't adjust anything below the
    ```php
    // NO CHANGES NEEDED BELOW THIS LINE
    ```
    line until you consider yourself a programmer and would like to change the way a script works.
* Run the script with
  ```bash
  php -f {scriptOfYourChoice}
  ```
  * You can consider reading input from a file and/or saving output to a log file, e.g. with:
    ```
    php -f import_metadata_sample.php < inputData 2>&1 | tee logFile
    ```
    (see the section below for hints on the input file format)

#### Running on repo-ingestion@hephaistos

* Make a copy and adjust content of the ingestion script.
  (from your PC perspective scripts can be found in the `ARCHE/script_templates` directory on the `acdh_resources` network drive).
* Log into `repo-ingestion@hephaistos`.
* Run
  ```bash
  screen -S mySessionName ./login.sh
  ```
* Consider preparing an input file - see the "Long runs" section below.
* Go to the directory with the script and run it, e.g.
  ```
  php -f import_metadata_sample.php 2>&1 | tee logFile
  ```
* Leave the `screen` session by hitting `CTRL+a` followed by `d`.
* To go back to the shell where you script is running run
  ```bash
  screen -r mySessionName
  ```

### Long runs

If you are performing time consuming operations, e.g. a large data ingestion, you may consider running scripts in a way they won't stop when you turn your computer off.

You can use `nohup` or `screen` for that, e.g.:

* nohup - run with:
  ```
  # console script variant
  nohup vendor/bin/arche-import-metadata --concurrency 4 myRdf.ttl https://arche.acdh.oeaw.ac.at/api myLogin myPassword > logFile 2>&1 &
  # template variant
  nohup php -f import_metadata_sample.php < input > logFile 2>&1 &
  ```
  * If you want to run template script variants that way, you **have to** prepare the input data file.  
    It should look as follows:
    ```
    {arche instance API URL}
    yes
    {login}
    {password}
    ```
    e.g.
    ```
    https://arche-dev.acdh-dev.oeaw.ac.at
    yes
    myLogin
    myPassword
    ```
* screen
  * start a `screen` session with
    ```bash
    screen -S mySessionName
    ```
  * Then run your commands as usual
  * Hit `CTRL+a` followed by a `d` to leave the `screen` session.
  * You can get back to the `screen` session with
    ```bash
    screen -r mySessionName
    ```

## Reporting errors

Create a subtask of the Redmine issue [#17641](https://redmine.acdh.oeaw.ac.at/issues/17641).

* Provide information on the exact location of the ingestion script location (including the script file itself) and any other information which may be required to replicated the problem.
* Assign Mateusz and Norbert as watchers.


### Runinng under repo-ingestion@hephaistos.arz.oeaw.ac.at

Skip the instructions above.

Copy a current template from this directory into your collection import scripts directory
and follow instructions for the "you want to save the settings inside the script" variant.

When adjusting settings at the top of a file leave `$configLocation` and `$composerLocation` as they are.

## Using arche-update-redmine in a GitHub workflow

The basic idea is to execute data processing steps in a following way:

* note down the step name so we can read it instead of a failure
* perform the step
* call the arche-update-redmine

and have a separate on-failure job step which makes an arche-update-redmine call noting the faillure.

Remarks:

* As a good practice we should include the GitHub job URL in the Redmine issue note.
  For that we set up a dedicated environment variable.
* It goes without saying Redmine access credentials are stored as a repository secret.
* The way you store the main Redmine issue ID doesn't matter as it's not secret.
  Do it a way you want (here we just hardcode it in the workflow using an environment variable)

```yaml
name: sample

on:
  push: ~

jobs:
  dockerhub:
    runs-on: ubuntu-latest
    env:
      REDMINE_ID: 21085
    steps:
    - uses: actions/checkout@v3
    - name: init
      run: |
        composer require acdh-oeaw/arche-ingest
        echo "RUN_URL=$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID" >> $GITHUB_ENV
    - name: virus scan
      run: |
        echo 'STEP=Virus Scan' >> $GITHUB_ENV
        ...perform the virus scan...
        vendor/bin/arche-update-redmine --token ${{ secrets.REDMINE_TOKEN }} --append "$RUN_URL" $REDMINE_ID 'Virus scan'
    - name: repo-filechecker
      run: |
        echo 'STEP=Run repo-file-checker' >> $GITHUB_ENV
        ...run the repo-filechecker...
        vendor/bin/arche-update-redmine --token ${{ secrets.REDMINE_TOKEN }} --append "$RUN_URL" $REDMINE_ID 'Run repo-file-checker'
    - name: check3
      run: |
        echo 'STEP=Upload AIP to Curation Instance (Minerva)' >> $GITHUB_ENV
        ...perform the ingestion...
        vendor/bin/arche-update-redmine --token ${{ secrets.REDMINE_TOKEN }} --append "$RUN_URL" $REDMINE_ID 'Upload AIP to Curation Instance (Minerva)' 
    - name: on failure
      if: ${{ failure() }}
      run: |
        vendor/bin/arche-update-redmine --token ${{ secrets.REDMINE_TOKEN }} --append "$RUN_URL" --statusCode 1 $REDMINE_ID "$STEP"

```
