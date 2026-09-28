variable "proxmox_endpoint" {
  type        = string
  description = "Proxmox API URL, such as https://pve.example.org:8006/"
}
variable "node_name" {
  type        = string
  description = "Proxmox node that will run Faved"
}
variable "source_node_name" {
  type        = string
  default     = null
  description = "Node containing CT 1000; null when the same as node_name"
}
variable "container_id" {
  type        = number
  description = "New, unused CT ID"
}
variable "datastore_id" {
  type        = string
  description = "Storage for the full clone"
}
variable "bridge" {
  type    = string
  default = "vmbr0"
}
variable "vlan_id" {
  type    = number
  default = null
}
variable "ipv4_address" {
  type    = string
  default = "dhcp"
}
variable "ipv4_gateway" {
  type    = string
  default = null
}
variable "root_ssh_public_key" {
  type        = string
  description = "SSH public key for bootstrap only; rotate after operator accounts exist"
}
