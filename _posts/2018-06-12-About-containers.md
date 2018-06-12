---
layout: post
title: Notes on docker and K8s
---

#### Docker in production

We have been using Docker 17.06 in production successfuly for more than 9 month already.
I wanted to share some good practices, they are easy to find but having them all in one place
is convenient. This is also reminder for me on the issues discovered and fixes applied.

#### Kubernetes

While docker compose is okay solution for small services, if you have a team and you starting/migrating
a project, please look into Kubernetes and [CNCF](https://www.cncf.io) it has [many good tools](https://landscape.cncf.io) already
built by the community.

#### Docker on Windows

Docker runs on Windows 10 PRO because it uses HyperV virtualization.
- You cant run both DockerMachine and HyperV native docker at the same time (HyperV and Virtualbox together) (June 2018)
- HyperV requires [some manual](https://docs.docker.com/machine/get-started/#prerequisite-information) steps due to networking adapter setup
- While Docker itself works combining it with [LSW (Linux Subsystem for Windows)](https://docs.microsoft.com/en-us/windows/wsl/install-win10) is tricky
  - Git (git-scm) needs to be installed from https://git-scm.com/ and ENABLE symlink support
  - Would have to use PowerShell/CMD for git commands, Windows 10 NTFS symlinks are not supported under LSW
  - Symlinks require enabling developer mode in Windows 10, and editing LocalSecurityPolicy to allow creating symlinks, 
  - Its better to disable UAC due to performance penalties working under LSW
  - You would have to recreate local repository with `git clone -c core.symlinks=true https://github.com/myuser/repo.git`
  - Black magic in form of adding windows account environment variables: `"CYGWIN=winsymlinks:nativestrict"` and `"MSYS=winsymlinks:nativestrict"`
  - Would have to install [cmder terminal emulator](http://cmder.net) instead of LSW one
  - Clipboard LSW copy-paste was only added in latest > 18xxx revision of Windows 10

Generally while Docker and LSW work great separately, combining them into environment is tricky.
Especially if you plan to run Kubernetes at the same time (same machine), as Kubernetes doesnt support
propert folder mounts still on Windows 10 (June 2018), even if you manage to mount some folders - 
you will start getting lock/error on file access and symlink issues.  

#### Docker articles

- [Docker Cheat Sheet](https://github.com/wsargent/docker-cheat-sheet)
- [Docker Engine Security Cheat Sheet](https://github.com/konstruktoid/Docker/blob/master/Security/CheatSheet.adoc)
- [Best practices for writing Dockerfiles](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Dockerfile reference](https://docs.docker.com/engine/reference/builder/)

#### Docker

You cant specify resources limits in Dockerfile, you would have to use docker-compose or cli arguments.
Practical workaround is launching docker containers as services with [systemd](https://en.wikipedia.org/wiki/Systemd).

Example systemd service  
- Fetches latest image on start from AWS ECR
- ECR credentials are provided by EC2 role under root user
- Memory is limited to 500mb, swap usage is restricted
- Host networking is used for better performance
- We dont want kernel to kill processes inside our container in case host runs out of memory
- Delete container data when container stops, container is stateless
- Mount data folder readonly
- Explicitly specify ports and protocol 
- Container is configured to react to SIGPWR as stop signal, we wait 45 seconds for gracefull stop
- We dont restart container if it dies abnormally, if there is problem we need to fix it
- We start our container/service after entering multi-user mode (often 'default' on normal boot)

{% highlight bash %}
[Unit]
Description=Docker App
After=docker.service
Requires=docker.service

[Service]
TimeoutStartSec=45
Type=simple
ExecStartPre=-/bin/bash -a -c "eval /usr/bin/aws ecr get-login --no-include-email --region us-east-1 | /bin/sed 's/docker/\/usr\/bin\/docker/' > /tmp/aws-ecr.sh"
ExecStartPre=-/bin/bash -a -c "/bin/chmod +x /tmp/aws-ecr.sh; /tmp/aws-ecr.sh; /bin/rm /tmp/aws-ecr.sh"
ExecStartPre=-/usr/bin/docker pull 999999999999.dkr.ecr.us-east-1.amazonaws.com/myrepo-image1:latest
ExecStart=/usr/bin/docker run -t \
                              --name myapp1 \
                              --rm          \
                              --network host \
                              --memory 500m \
                              --memory-swap 500m \
                              --oom-kill-disable \
                              --mount type=bind,source=/var/www/mydata,target=/var/www/mydata,readonly \
                              -p 80:80/tcp \
                              -e "ENV_A1=1" \
                              -e "ENV_A2=2" \
                              --ulimit nofile=65535:65535    \
                              --stop-signal=SIGPWR           \
                              999999999999.dkr.ecr.us-east-1.amazonaws.com/myrepo-image1:latest
ExecReload=/usr/bin/docker restart -t 45 myapp1
ExecStop=/usr/bin/docker stop -t 45 myapp1
TimeoutStopSec=50s
LimitNOFILE=infinity
LimitNPROC=infinity
LimitCORE=infinity
Delegate=yes
KillMode=process
KillSignal=SIGPWR
Restart=on-abnormal
StartLimitBurst=3
StartLimitInterval=5s

[Install]
WantedBy=multi-user.target
{% endhighlight %}


Healthchecks with start period, docker fires up containers but they might take time to initialize.
This is better handled in Kubernetes, docker only has HEALTHCHECK in Dockerfile.

{% highlight bash %}
HEALTHCHECK --interval=5s --retries=2 --start-period=10s --timeout=5s CMD sleep 2 && curl -S -s -f http://127.0.0.1/ping && echo " ok" || exit 1
{% endhighlight %}

EXPOSE instruction informs Docker that the container listens on the specified network ports at runtime
The EXPOSE instruction does not actually publish the port.
EXPOSE is help for people trying to use your container.


FROM can be dynamic
{% highlight bash %}
ARG DOCKER_BASE=999999999999.dkr.ecr.us-east-1.amazonaws.com/myrepo-image1:latest
FROM $DOCKER_BASE
{% endhighlight %}

Try to run your apps as non-root user, this user doesnt have to exist on host at all

{% highlight bash %}
#....
RUN addgroup -g 1111 -S dalek && adduser -u 1111 -S -G dalek dalek
#.... other things needed to be run as root i.e. enabling sudo
RUN        echo "dalek ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
#.... switch to our user inside Dockerfile 
USER       dalek
#....
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD        ["myapp1", "--myargument1"]
{% endhighlight %}

#### Kubernetes/Minikube local setup on Windows (Virtualbox)

To install Minikube use [Chocolatey](https://chocolatey.org/install)

{% highlight bash %}
choco install minikube --version 0.25.2 --allow-downgrade
{% endhighlight %}

Or download directly from 
[https://github.com/kubernetes/minikube/releases/download/v0.25.2/minikube-windows-amd64](https://github.com/kubernetes/minikube/releases/download/v0.25.2/minikube-windows-amd64)
and rename to minikube.exe and place inside PATH (i.e. your windows home folder)

Use 0.25.2 version (June 2018) as later versions switched to KubeAdm for creating local cluster,
while 0.25.2 defaults to localcube.

Install Kubernetes CLI via Chocolatey
{% highlight bash %}
choco install kubernetes-cli
{% endhighlight %}

Or download directly from
[https://storage.googleapis.com/kubernetes-release/release/v1.10.0/bin/windows/amd64/kubectl.exe](https://storage.googleapis.com/kubernetes-release/release/v1.10.0/bin/windows/amd64/kubectl.exe)
and place inside PATH (i.e. your windows home folder)

Setup minikube settings, recommended minimum memory >2.8Gb and k8s version 1.10.0 (LTS)

{% highlight bash %}
    minikube.exe config set kubernetes-version v1.10.0
    minikube.exe config set WantReportError false
    minikube.exe config set WantUpdateNotification false
    minikube.exe config set memory 3072
    minikube.exe config set vm-driver virtualbox
    minikube.exe config set cpus 2
    minikube.exe config view
{% endhighlight %}

Start cluster by typing inside PowerShell, 
- localkube bootstrapper is deprecated but stable for 1.10.0 
- host-only-cidr is needed to fix cluster networking issues while restoring (wake-up)

{% highlight bash %}
minikube.exe start --bootstrapper localkube --host-only-cidr="192.168.99.1/26"
{% endhighlight %}

After launching i recommend disabling all addons (requires restart), as many of them are not RBAC enabled
{% highlight bash %}
    minikube.exe addons disable registry-creds
    minikube.exe addons disable dashboard
    minikube.exe addons disable heapster
    minikube.exe addons disable kube-dns
    minikube.exe stop    
{% endhighlight %}

Or manually edit `~/.minikube/config/config.json` before starting cluster
{% highlight bash %}
    {
         "heapster": false,
         "registry-creds": false,
         "dashboard": false,
         "kube-dns": false
    }
{% endhighlight %}

After cluster starts, use forwarding to access your services
{% highlight bash %}
#Dashboard if installed (http://localhost:8001/api/v1/namespaces/kube-system/services/kubernetes-dashboard/proxy)
#kubectl proxy

#Point port 80 to ingress, edit your hosts file to use name-besed virtual hosts with kubernetes
kubectl.exe port-forward -n mynamespace1 svc/nginx-ingress 80:80  
{% endhighlight %}

Misc commands

{% highlight bash %}
minikube.exe stop
minikube.exe delete 
kubectl.exe config view --flatten
kubectl.exe config use-context mycluster-name
{% endhighlight %}

#### Summary

Overall we didnt encountered any issues with running docker, it's stable and we continue to migrate to K8S.
If you dont use Docker i highly recommend it for your local environment and both beta/production.
Kubernetes 1.10.0 release is an LTS release and is mainstream now (AWS EKS GA in June 2017).

#### Links
- [Kubernetes](https://kubernetes.io)
- [Events/Conferences](https://events.linuxfoundation.org)
- [CNCF (CloudNativeComputingFoundation)](https://www.cncf.io)
- [CNCF Youtube](https://www.youtube.com/channel/UCvqbFHwN-nwalWPjPUKpvTA)
