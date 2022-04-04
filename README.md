# nod32update-mirror
ESET Nod32 Updates Mirror based on nginx:stable-alpine and php script https://github.com/Kingston-kms/eset_mirror_script


Build image container:
>docker build -t gerain/nod32update-mirror .


Run container:
>docker run -d -p 8084:80 -v nod32update-mirror:/nod32update --restart always --name nod32update-mirror gerain/nod32update-mirror
