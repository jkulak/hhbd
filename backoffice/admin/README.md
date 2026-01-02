## How to run locally

- Set proper db values in config folder. Available file template `app.ini.example`
- Replace in **Dockerfile** `${PORT}` with `8080` port
- Build docker image - in **Dockerfile** directory command `docker build -t admin:v1 . `
- Run container `docker run -p8080:8080 admin:v1`
