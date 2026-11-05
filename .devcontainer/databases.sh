#!/bin/bash
# Starts the databases of the GitHub Actions workflow as Docker containers: PostgreSQL 17 (user postgres, no
# password) and MariaDB 11 (user root, password root), on their usual ports. Safe to run again.

start() {
    local name=$1
    shift
    if [ -n "$(docker ps -q -f name="^${name}$")" ]; then
        return
    fi
    if [ -n "$(docker ps -aq -f name="^${name}$")" ]; then
        docker start "$name" > /dev/null
    else
        docker run -d --name "$name" --restart unless-stopped "$@" > /dev/null
    fi
}

# Docker in the codespace can take a moment to start.
for i in $(seq 1 30); do
    docker info > /dev/null 2>&1 && break
    sleep 2
done

start lm-postgres -p 5432:5432 -e POSTGRES_USER=postgres -e POSTGRES_HOST_AUTH_METHOD=trust postgres:17
start lm-mariadb -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_CHARACTER_SET_SERVER=utf8mb4 \
    -e MYSQL_COLLATION_SERVER=utf8mb4_unicode_ci mariadb:11 \
    --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

for i in $(seq 1 30); do
    pg_isready -h 127.0.0.1 -q && mariadb-admin -h 127.0.0.1 -uroot -proot ping > /dev/null 2>&1 && break
    sleep 2
done
echo "Databases: PostgreSQL 17 and MariaDB 11 running"
