FROM mysql:8
RUN echo "[mysqld]" >> /etc/mysql/my.cnf
RUN echo "default-authentication-plugin=mysql_native_password" >> /etc/mysql/my.cnf
RUN echo "log_error = /var/log/mysqld_error.log" >> /etc/mysql/my.cnf