FROM nginx:stable-alpine
RUN apk update && apk upgrade && apk add --no-cache \
git php php-curl php-fileinfo php-iconv php-mbstring php-openssl pcre php-simplexml php-sockets php-zlib php-json
WORKDIR /nod32update
COPY nod32ms.conf eset_mirror_script/ ./
COPY default.conf /etc/nginx/conf.d/default.conf
