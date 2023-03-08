### TYPO3 Docker Builder

Use the `./t3-container` script to build a Docker container for TYPO3 v10 and later.
This little wrapper is used to feed the `Dockerfile` with all required
variables (TYPO3 version, PHP modules, PHP version). PHP modules and PHP version will
be composed of requirements found in [composer.json](https://raw.githubusercontent.com/TYPO3/typo3/main/composer.json). 

:warning: At the moment this is just an experiment! Use at your own risk. 

This will not cover an "everyone and their dog"-setup. 
It is more of a base image to start with TYPO3 and Docker.

## Build a image

Build `dev-main`:
```
./t3-container
```

Build `v12.1` (will be the image tag) which will pick the latest release of `v12.1.x` :
```
./t3-container -v 12.1
```

Build a specific version `v12.1.1` (will be the image tag):
```
./t3-container -v 12.1.1
```

## Additional `./t3-container` script options

| Option | Description                                                                                                                                                                                                                                  |
|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| -v     | TYPO3 version to use. If set to 'v12.2' the latest version of 'v12.2.x' will be picked and both versions are tagged                                                                                                                          |
| -m     | Add additional PHP modules to the the build, e.g. '-m "intl opcache"'. Usually, the dependencies are read from `composer.json`. If one of the module is already available in the base image (`php:<php_version>-apache`) it will be ignored! |
| -x     | Build mutli-arch image. Currently hardcoded and set to arm64 and amd64                                                                                                                                                                       |
| -p     | Push image after successful build                                                                                                                                                                                                            |

All options example:

```bash
./t3-container -v v12.2 -m "intl intl intl intl intl opcache opcache" -x -p
```

## Run the container

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
  * Create example docker-compose.yml
  * Kubernetes/Helm chart
