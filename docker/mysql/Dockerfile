FROM mysql:8
RUN echo "[mysqld]" >> /etc/mysql/my.cnf
RUN echo "default-authentication-plugin=mysql_native_password" >> /etc/mysql/my.cnf
RUN echo -e "general_log_file = /var/log/mysqld_general.log\ngeneral_log = 1\nlog_error = /var/log/mysqld_error.log" >> /etc/mysql/my.cnf