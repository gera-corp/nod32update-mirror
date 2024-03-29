# nod32update-mirror
ESET Nod32 Updates Mirror based on nginx:stable-alpine and php script https://github.com/Kingston-kms/eset_mirror_script


Build image container:
```sh
docker build -t gerain/nod32update-mirror .
```

Run container:
```sh
docker run -d -p 8084:80 -v nod32update-mirror:/nod32update/www --restart always --name nod32update-mirror gerain/nod32update-mirror
```
To configure the nod32ms.conf script with its own values, after the change, run the container:
```sh
docker run -d -p 8084:80 -v ./nod32ms.conf:/nod32update/nod32ms.conf -v nod32update-mirror:/nod32update/www --restart always --name nod32update-mirror gerain/nod32update-mirror
```

Update nod32 antivirus bases:
```sh
docker exec nod32update-mirror php update.php
```

>you can add cronjob to docker-host to update antivirus databases every two hours:
>```sh
>crontab -e
>```
>0 */2 * * * docker exec nod32update-mirror php update.php

Open in your browser:
>http://youip:8084
