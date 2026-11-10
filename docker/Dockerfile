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
RUN apt-get --no-install-recommends -y install php8.5-cli php8.5-mysql \
 php8.5-memcached memcached php8.5-gmp php8.5-gd php-pear php8.5-readline php8.5-curl php8.5-xdebug \
 php8.5-mbstring php8.5-xml
# Set timezone
RUN echo "Europe/Copenhagen" > /etc/timezone
RUN dpkg-reconfigure tzdata
ENTRYPOINT ["/srv/mom/docker/run-mom-unit-test"]
