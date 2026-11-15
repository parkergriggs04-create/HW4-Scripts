CREATE TABLE IF NOT EXISTS students (
  id                 SERIAL PRIMARY KEY,
  first              VARCHAR(100) NOT NULL,
  last               VARCHAR(100) NOT NULL,
  email              VARCHAR(255) NOT NULL UNIQUE,
  birthdate          DATE NOT NULL,
  date_of_enrollment DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS courses (
  credits INTEGER      NOT NULL CHECK (credits BETWEEN 1 AND 6)
);

CREATE TABLE IF NOT EXISTS course_prerequisites (
);

CREATE TABLE IF NOT EXISTS enrollments (
);
