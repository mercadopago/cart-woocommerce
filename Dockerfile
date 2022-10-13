FROM hub.furycloud.io/mercadolibre/melibuntu:latest

WORKDIR /

# Thinks and to do on build, output will be the folder that will be used in production

ONBUILD RUN mkdir -p /output
ONBUILD COPY ./command.sh /output/command.sh

ADD ./ /app

## Commands to run by fury on "fury run/test/build"
ADD run.sh /commands/run.sh
ADD test.sh /commands/test.sh
ADD build.sh /commands/build.sh




