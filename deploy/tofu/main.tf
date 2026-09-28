terraform {
  required_version = ">= 1.8.0"
  required_providers {
    proxmox = {
      source  = "bpg/proxmox"
      version = "= 0.114.0"
    }
  }
}

provider "proxmox" {
  endpoint = var.proxmox_endpoint
  # Set PROXMOX_VE_API_TOKEN in the runner environment.
}

resource "proxmox_virtual_environment_container" "faved" {
  node_name     = var.node_name
  vm_id         = var.container_id
  description   = "Faved self-hosted; managed by OpenTofu and Ansible"
  unprivileged  = true
  started       = true
  start_on_boot = true
  protection    = true

  clone {
    vm_id        = 1000
    node_name    = var.source_node_name
    datastore_id = var.datastore_id
    full         = true
  }

  features {
    nesting = true
    keyctl  = true
  }

  cpu {
    cores = 2
  }
  memory {
    dedicated = 2048
    swap      = 512
  }
  disk {
    datastore_id = var.datastore_id
    size         = 16
  }
  network_interface {
    name     = "eth0"
    bridge   = var.bridge
    firewall = true
    vlan_id  = var.vlan_id
  }
  initialization {
    hostname = "faved"
    ip_config {
      ipv4 {
        address = var.ipv4_address
        gateway = var.ipv4_address == "dhcp" ? null : var.ipv4_gateway
      }
    }
    user_account {
      keys = [trimspace(var.root_ssh_public_key)]
    }
  }

  lifecycle {
    prevent_destroy = true
  }
}

output "container_id" {
  value = proxmox_virtual_environment_container.faved.vm_id
}
