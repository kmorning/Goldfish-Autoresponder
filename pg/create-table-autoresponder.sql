CREATE TABLE autoresponder (
    email varchar(255) PRIMARY KEY, 
    descname varchar(255) default NULL,
    from_date date NOT NULL default '19990108',
    to_date date,
    message text NOT NULL,
    subject varchar(255) NOT NULL default '',
    force_disabled boolean NOT NULL default false
);
