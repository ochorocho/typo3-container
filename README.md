### TYPO3 Docker Builder

Use the `./bin/t3-container` script to build a Docker container for TYPO3 v11 and later.
This little wrapper is used to feed the `Dockerfile` with all required
variables (TYPO3 version, PHP modules, PHP version). PHP modules and PHP version will
be composed of requirements found in [composer.json](https://raw.githubusercontent.com/TYPO3/typo3/main/composer.json). 

:warning: At the moment this is just an experiment! But the idea is to have a production ready TYPO3 container image.

    For now this will not cover an "everyone and their dog"-setup.
    It is more of a base image to start with TYPO3 and Docker and try to
    provide more and more stuff as we go.

## Build a image

Build `dev-main`:
```
./bin/t3-container build ochorocho/typo3-container dev-main
```

Build `v12.1` (will be the image tag) which will pick the latest release of `v12.1.x` :
```
./bin/t3-container build ochorocho/typo3-container 12.1
```

Build a specific version `v12.1.1` (will be the image tag):
```
./bin/t3-container build ochorocho/typo3-container 12.1.1
```

## Image tags

The image will be built once a day by a GitHub Action. This might change to once a week.
Depending on how often things change.

Images will be tagged depending on the requested version (`-v`).
If the given version is `v12` tags for `v12` `v12.x` and `v12.x.x` where `x` is the latest version available on build time.

Image version example:

| Tag                    | TYPO3 version               | PHP version |
|------------------------|-----------------------------|-------------|
| `latest` or `dev-main` | dev-main                    | 8.1         |
| `v12`                  | v12.x.x (latest v12)        | 8.1         |
| `v12.2`                | v12.2.x (latest v12.2)      | 8.1         |
| `v12.2.0`              | v12.2.1 (specific version)  | 8.1         |
| `v11`                  | v11.x.x (latest v11)        | 8.0         |
| `v11.5`                | v11.5.x (latest v11.5)      | 8.0         |
| `v11.5.24`             | v11.5.24 (specific version) | 8.0         |
| ...                    | ...                         | ...         |


## Minimal command

```bash
./bin/t3-container build <container-name> <version>
````

## Command arguments

| Argument         | Description                                                                                                         |
|------------------|---------------------------------------------------------------------------------------------------------------------|
| <container-name> | The container name, e.g. `ochorocho/typo3-container`                                                                |
| <version>        | TYPO3 version to use. If set to 'v12.2' the latest version of 'v12.2.x' will be picked and both versions are tagged |


## Command options

| Option | Description                                                                                                                                                                                                                                  |
|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| -x     | Build multi-arch image                                                                                                                                                                                                                       |
| -p     | Push image to docker hub after successful build                                                                                                                                                                                              |
| -l     | Load image into docker                                                                                                                                                                                                                       |
| -m     | Add additional PHP modules to the the build, e.g. '-m "intl opcache"'. Usually, the dependencies are read from `composer.json`. If one of the module is already available in the base image (`php:<php_version>-apache`) it will be ignored! |
| -c     | Add additional composer packages, e.g. '-c "typo3/cms-webhooks:dev-main typo3/cms-reactions:dev-main"'                                                                                                                                       |
| -e     | Container engine to use for building the image. Possible values are "docker" or "podman"                                                                                                                                                     |
| -a     | Architectures to build, e.g "-a linux/arm64 -a linux/amd64".                                                                                                                                                                                 |

All options example:

```bash
./bin/t3-container build ochorocho/typo3-container dev-main -m "intl opcache" -e podman -a linux/arm64 -c "typo3/cms-webhooks:dev-main typo3/cms-reactions:dev-main" -x -p
```

## Run the container

The container comes with TYPO3 preinstalled in `/var/www/html`. So you can issue a single command to run TYPO3:

```
docker run -d --name <container-name> -p 3333:80 ochorocho/typo3-container:v12
```

Login as root:

```
docker exec -it -u 0 <container-name> bash
```

## Todo

  * Add composer version switch
  * Allow to force PHP version
  * Allow to add additional packages
  * Allow to add additional docker-ext-configure arguments
  * Create example docker-compose.yml (mysql, mariadb, postgresql)
  * Kubernetes/Helm chart
  * Test the images. Idea: start the container image and go through the setup and see the backend.
