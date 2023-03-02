#### 代理进程
#创建一个名叫service-manage 命名空间在kube-system 下的服务账户
kubectl create serviceaccount service-manage -n kube-system

#service-manage 绑定为集群账户
kubectl create clusterrolebinding service-manage --clusterrole=cluster-admin --serviceaccount=kube-system:service-manage

#显示出名字为service-manage-*的第一个匹配账户的详细信息
kubectl describe secrets -n kube-system $(kubectl -n kube-system get secret | awk '/service-manage/{print $1}')