--
-- Database: pulltester
--


CREATE TABLE phpCsResults (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pulls_id int(11) NOT NULL,
  errors int(11) NOT NULL,
  warnings int(11) NOT NULL
);


CREATE TABLE phpunitResults (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pulls_id int(11) NOT NULL,
  tests int(11) NOT NULL,
  assertions int(11) NOT NULL,
  failures int(11) NOT NULL,
  errors int(11) NOT NULL,
  time REAL NOT NULL
);


CREATE TABLE pulls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_id INTEGER NOT NULL,
  head TEXT NOT NULL,
  base TEXT NOT NULL,
  mergeable INTEGER NOT NULL,
  user TEXT NOT NULL,
  title TEXT NOT NULL,
  avatar_url TEXT NOT NULL,
  data TEXT NOT NULL
);
