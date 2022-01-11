# A collection of ARCHE ingestion script templates

The REST API provided by the ARCHE is quite a low-level from the point of view of real-world data ingestions.
To make ingestions simpler, the [arche-lib-ingest](https://github.com/acdh-oeaw/arche-lib-ingest) library has been developed.
While it provides a convenient high-level data ingestion API, it's still only a library which requires you to write your own ingestion script.

This repository is aimed at closing this gap - it provides a set of sample data ingestion scripts (using the arche-lib-ingest)
which can be used by people with almost no programming skills.

## Sample scripts provided

* `add_metadata_sample.php` adds metadata triples specified in the ttl file preserving all existing metadata of repository resources
* `delete_metadata_sample.php` removes metadata triples specified in the ttl file (but doesn't remove repository resources)
* `delete_resource_sample.php` removes a given repository resource
* `import_binary_sample.php` imports binary data from the disk
* `import_metadata_sample.php` imports metadata from an RDF file
* `reimport_single_binary.php` reingests a single resource's binary content (to be used when file name and/or location changed)

## Reporting errors

Create a subtask of the Redmine issue [#17641](https://redmine.acdh.oeaw.ac.at/issues/17641).

* Provide information on the exact location of the ingestion script location (including the script file itself) and any other information which may be required to replicated the problem.
* Assign Mateusz and Norbert as watchers.

## Usage

There are two usage scenarios:

1. When you want to preserve the settings inside a file (e.g. as a documentation of the ingestion process).
2. When you want to pass setting from the command line while running a given script (e.g. when you run it inside a CI/CD workflow or interactively).

In the first case:

* Clone this repository.
* Run `composer update` in the repository root directory.
* Optionally prepare a configuration file with a list of repositories you want to ingest against (see the `config-sample.yaml` file).
* Choose the `*_sample.php` script you want to use, open it and adjust configuration settings in its top section.
* Run the script with `php -f scriptOfYourChoice`, e.g. `php -f import_metadata_sample.php`.

In the second case:

* Run `composer require acdh-oeaw/arche-ingest`.
* Choose the script you want to use out of 
  `bin/arche-import-metadata` (a wrapper for `import_metadata_sample.php`), 
  `bin/arche-import-binary` (a wrapper for `import_binary_sample.php`) and
  `bin/arche-delete-resource` (a wrapper for `delete_resource_sample.php`)
  and run it with `vendor/bin/scriptOfYourChoice -- parameters go here`, e.g.
  `vendor/bin/arche-import-metadata --concurrency 4 myRdf.ttl https://arche.acdh.oeaw.ac.at/api myLogin myPassword`
  * You can check required and optional parameters by running the script with the `-h` parameter, e.g.
    `vendor/bin/arche-import-metadata -h`

### Running inside GitHub Actions

Follow the second scenario described above.

Do not store your ARCHE credentials in the workflow configuration file. Use repository secrets instead (see example below).

A fragment of your workflow;s yaml config may look like that:

```yaml
    - name: ingestion  dependencies
      run: |
        composer require "acdh-oeaw/arche-ingest
    - name: ingest arche
      run: |
        vendor/bin/arche-import-metadata myRdfFile.ttl https://arche-dev.acdh-dev.oeaw.ac.at/api ${{secrets.ARCHE_LOGIN}} ${{secrets.ARCHE_PASSWORD}}
```

### Runinng under repo-ingestion@hephaistos.arz.oeaw.ac.at

Skip the instructions above.

Copy a current template from this directory into your collection import scripts directory
and follow instructions for the "you want to save the settings inside the script" variant.

When adjusting settings at the top of a file leave `$configLocation` and `$composerLocation` as they are.

#### If the ingestions takes long

* Prepare a file with input data.  
  In points below it's assumed this file name is `input` and it's stored in the same directory as the import script.  
  The file should contain of four lines:
  ```
  {instanceNumber}
  yes
  {yourLogin}
  {yourPassword}
  ```
  where `{instanceNumber}` is `1` for the development instance, `2` for the curation instance and `3` for the production instance, e.g.
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
