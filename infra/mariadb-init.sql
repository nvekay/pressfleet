-- One MariaDB server, one database per demo site
CREATE DATABASE IF NOT EXISTS site1;
CREATE DATABASE IF NOT EXISTS site2;
CREATE DATABASE IF NOT EXISTS site3;
GRANT ALL PRIVILEGES ON site1.* TO 'pressfleet'@'%';
GRANT ALL PRIVILEGES ON site2.* TO 'pressfleet'@'%';
GRANT ALL PRIVILEGES ON site3.* TO 'pressfleet'@'%';
