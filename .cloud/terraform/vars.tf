variable project {
  type        = string
  description = "The Google Cloud Platform project id"
}

variable service {
  description = "Name of the service"
  type        = string
}

variable region {
  default = "europe-west1"
  type    = string
}

variable instance_name {
  description = "Name of the postgres instance (PROJECT_ID:REGION:INSTANCE_NAME))"
  type        = string
}
