# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
    config.vm.box = "quantal64"
    config.vm.provision :shell, :path => "scripts/provision"
    config.vm.synced_folder ".", "/vagrant"
end
