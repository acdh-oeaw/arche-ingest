# Using the arche-ingestion environment on the ACDH cluster

There are two ways to obtain a console of the arche-ingestion environment on the ACDH cluster:

* By using the Rancher GUI  
  This method is easier to start with but has two annoying features.
  First, the console is being disconnected after annoyingly short period of inactivity
  and second, copy-pasting does not work with `CTRL+c` and `CTRL+v`.
* By using the `kubectl`  
  This method is harder to set up but provides a nicer user experience.

## Rancher GUI

* Open https://rancher.acdh-dev.oeaw.ac.at/ and if needed log in using your OEAW credentials.
* Open https://rancher.acdh-dev.oeaw.ac.at/dashboard/c/c-m-6hwgqq2g/explorer/apps.deployment/arche-ingestion/arche-ingestion.
* Click on the bluish button with three vertical dots in the top-right corner of the screen and choose `> Execute Shell`

Remarks:

* As the shell gets disconnected after a very short inactivity, remember to run commands using `screen`:
  * Run `screen -S mySessionName`, then hit `enter`.
  * Run commands as normal.
  * If the shell gets disconnected, obtain a new one and run `screen -rd mySessionName`
* You can not use `CRTL+c` and `CTRL+v`. Copying and pasting is only possible using the righ-mouse-button menu.

## Kubectl

### Installation

* Install the `kubectl`: https://kubernetes.io/docs/tasks/tools/
* Open https://rancher.acdh-dev.oeaw.ac.at/ and if needed log in using your OEAW credentials.
* Open https://rancher.acdh-dev.oeaw.ac.at/dashboard/c/_/manager/provisioning.cattle.io.cluster.
* In the table listing clusters click on the vertical three dots button at the right end of the table row
  and choose the `Download KubeConfig`.
  Remember the location where you have stored the file.
  This location will be referred to as `kubeConfigFilePath` in commands below.

### Usage

Run
```bash
pod=`kubectl get pods --namespace arche-ingestion --kubeconfig=kubeConfigFilePath | grep Running | head -n 1 | sed -e 's/ .*//'`
kubectl exec --kubeconfig=kubeConfigFilePath --stdin --tty --namespace arche-ingestion $pod -- /bin/bash
```
