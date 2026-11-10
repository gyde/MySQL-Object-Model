FROM debian:bookworm
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update
RUN apt-get --no-install-recommends -y install apt-utils
RUN apt-get --no-install-recommends -y install gnupg software-properties-common ca-certificates dirmngr apt-transport-https lsb-release ca-certificates curl
RUN apt-get update && apt-get --no-install-recommends -y install gnupg
# Install sury repo keys
RUN curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg && \
 sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
# Install base packages
RUN apt-get update && apt-get --no-install-recommends -y install wget vim-nox htop wget
# Install cli + memcached
RUN apt-get --no-install-recommends -y install php8.4-cli php8.4-mysql \
 php8.4-memcached memcached php8.4-gmp php8.4-gd php-pear php8.4-readline php8.4-curl php8.4-xdebug \
 php8.4-mbstring php8.4-xml
# Set timezone
RUN echo "Europe/Copenhagen" > /etc/timezone
RUN dpkg-reconfigure tzdata
ENTRYPOINT ["/srv/mom/docker/run-mom-unit-test"]
