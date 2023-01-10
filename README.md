### TYPO3 Docker Builder

Build each TYPO3 version into a Docker container.

:warning: At the moment this is just an experiment! Use at your own risk. 

This will not cover a "everyone and their dog"-setup. 
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

Build the specific version `v12.1.1` (will be the image tag):
```
./t3-container -v 12.1.1
```

## Run the container

```
docker run -p 3333:80 ochorocho/typo3-container:v12.1
```

Login as root:

```
docker exec -it -u 0 <container-name> bash
```
