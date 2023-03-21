#!/usr/bin/env bash
cd /tmp

git clone git@github.com:CloudNativePassPlatform/k8s-proxy.git

cd k8s-proxy

docker build -t registry.cn-hangzhou.aliyuncs.com/cnpp/proxy:$1 .

docker login --username=itxiao6@qq.com registry.cn-hangzhou.aliyuncs.com

docker push registry.cn-hangzhou.aliyuncs.com/cnpp/proxy:$1

cd ..

rm -fR k8s-proxy

echo "发布成功registry.cn-hangzhou.aliyuncs.com/cnpp/proxy:$1"