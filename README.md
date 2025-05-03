# TYPO3 Surfcamp Base 2025

This Git repository is intended for use by our dedicated teams at the [TYPO3 Surfcamp](https://surfcamp.typo3.com/).

The project is based on TYPO3 v13, using Composer. The configuration happens via `.env` thanks to the underlying package `vlucas/phpdotenv`.

## Requirements

* having Docker installed locally (see https://docs.docker.com/get-docker/)
* having DDEV installed locally (see https://ddev.readthedocs.io/en/stable/#installation)

## Initialization

```sh
ddev start
ddev composer install
ddev pull assets
```

## Credentials

- Backend: https://surfcamp-base-2025.ddev.site/typo3
- Username: `admin`
- Password: `John3:16`

### Downloading database and files

```sh
# HEADS UP: All files in the local `public/fileadmin/` will be overridden, that means:
# all files that are not present in `data/files/public/fileadmin/` will be deleted from fileadmin
ddev pull assets
```

### Update local database and files

```sh
# HEADS UP: All files in the local `data/files/public/fileadmin/` will be overridden, that means:
# all files that are not present in `public/fileadmin/` will be deleted from fileadmin
ddev push assets
```
