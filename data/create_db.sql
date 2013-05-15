create table jobs(
  id INT(10) PRIMARY KEY auto_increment, 
  type VARCHAR(50),
  context VARCHAR(50),
  queue VARCHAR(50), 
  state VARCHAR(50),
  progress int(10) DEFAULT'0',
  message VARCHAR(256),
  lastUpdate INT(10), 
  data BLOB DEFAULT ''
);
  
create table processes(
  id INT(10) PRIMARY KEY,
  pid INTEGER
);
