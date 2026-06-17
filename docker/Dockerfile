FROM debian:bookworm
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get --no-install-recommends -y install apt-utils ca-certificates
RUN apt-get update && apt-get -y install vim htop wget
# Install webserver
RUN apt-get --no-install-recommends -y install php-cli php-mysqlnd php-memcached php-dom memcached php-mbstring
# Install dbserver dependencies
RUN apt-get install --no-install-recommends -y software-properties-common gnupg
# Install dbserver key and repo
RUN apt-key adv --recv-keys --keyserver keyserver.ubuntu.com 0xcbcb082a1bb943db && \
 add-apt-repository 'deb [arch=amd64,i386,ppc64el] http://mirror.one.com/mariadb/repo/10.4/debian buster main'
# Create mysql debconf user & password
COPY container_configs/debconf /root/debconf
# Install mariadb
RUN debconf-set-selections /root/debconf && \
 apt-get --no-install-recommends -y install mariadb-server
# Set timezone
RUN echo "Europe/Copenhagen" > /etc/timezone
RUN dpkg-reconfigure tzdata
# Copy data
RUN echo 'new sql databases 2019-11-26 21.12'
COPY container_configs/users.sql /root/
RUN service mariadb start && export MYSQL_PWD=WindTauntCreme && \
 cat /root/users.sql | mysql -h localhost -u root
# Copy configuration files
ENTRYPOINT service memcached start && service mariadb start && /bin/bash
