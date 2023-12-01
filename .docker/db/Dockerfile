FROM mysql:8.0

# Set an insecure password
ENV MYSQL_ROOT_PASSWORD=root

# Copy over our SQL queries
COPY ./.docker/db/init.sql /init.sql

# Startup MySQL and run the queries
CMD ["mysqld", "--init-file=/init.sql"]
