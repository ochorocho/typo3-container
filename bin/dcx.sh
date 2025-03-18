#!/bin/bash

cd "$(pwd)" || exit 1

if [[ $1 == "-h" || $1 == "--help" || $1 == "" ]]; then
    echo "Usage: dcx [command]"
    echo "Commands:"
    echo "  start - Start the docker-compose setup"
    echo "  stop - Stop the docker-compose"
    echo "  logs - Show logs"
    echo "  restart - restart the docker-compose setup"
    echo "  reset - Reset the docker-compose !!! DESTRUTIVE !!!"
    # echo "  build - Build the container"
fi

if [[ $1 == "start" ]]; then
    docker-compose --file docker-compose.yaml up --detach --remove-orphans 
fi

if [[ $1 == "stop" ]]; then
    docker-compose down
fi

if [[ $1 == "logs" ]]; then
    docker-compose logs -f
fi

if [[ $1 == "restart" ]]; then
    docker-compose restart
fi

if [[ $1 == "reset" ]]; then
    read -p "Reset and delete all data from Typo3 and MariaDB? (y/N) " -n 1 -r REPLY
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker-compose down --volumes
    fi
fi
