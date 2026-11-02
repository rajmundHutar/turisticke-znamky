FROM mysql:8.0

# Set an insecure password
ENV MYSQL_ROOT_PASSWORD=root

# Schema for a fresh database; the entrypoint runs it only when the data directory is empty
COPY ./.docker/db/init.sql /docker-entrypoint-initdb.d/init.sql
