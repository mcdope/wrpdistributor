FROM rastasheep/ubuntu-sshd:18.04

RUN useradd -d /home/phpunit -s /bin/bash phpunit && \
    mkdir -p /home/phpunit/.ssh && \
    echo phpunit:phpunit | chpasswd && \
    chown -R phpunit:phpunit /home/phpunit

ADD phpunit.pub /home/phpunit/.ssh/authorized_keys
ADD fake-docker.sh /usr/bin/docker
RUN chown -R phpunit:phpunit /home/phpunit/.ssh && \
    chmod 0700 /home/phpunit/.ssh && \
    chmod 0600 /home/phpunit/.ssh/authorized_keys && \
    chmod +x /usr/bin/docker

