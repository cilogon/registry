#!/bin/bash
#
# Build containers for COmanage Registry and associated tools.
#
# Portions licensed to the University Corporation for Advanced Internet
# Development, Inc. ("UCAID") under one or more contributor license agreements.
# See the NOTICE file distributed with this work for additional information
# regarding copyright ownership.
#
# UCAID licenses this file to you under the Apache License, Version 2.0
# (the "License"); you may not use this file except in compliance with the
# License. You may obtain a copy of the License at:
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

###########################################################################
# Build the Registry base image.
# Globals:
#   None
# Arguments:
#   Full image name prefix, a string.
#   Tag label, a string.
#   Tag suffix, a string.
#   Docker build flags, other flags for docker build.
# Outputs:
#   None
###########################################################################
function build_base() {
    local docker_build_command
    local docker_build_flags
    local label
    local prefix
    local suffix

    prefix="$1"
    label="$2"
    suffix="$3"

    if [[ -z "${label}" ]]; then
        err "ERROR:build_base: label cannot be empty"
        return 1
    fi

    if [[ -z "${suffix}" ]]; then
        err "ERROR:build_base: suffix cannot be empty"
        return 1
    fi

    declare -a docker_build_flags=("${@:4}")

    tag="comanage-registry-base:${label}-${suffix}"

    docker_build_command=(docker build)

    if ((${#docker_build_flags[@]})); then
        for flag in "${docker_build_flags[@]}"; do
            docker_build_command+=("${flag}")
        done
    fi

    docker_build_command+=(--tag "${tag}")
    docker_build_command+=(--build-arg COMANAGE_REGISTRY_VERSION="${label}")
    docker_build_command+=(--file container/registry/base/Dockerfile)
    docker_build_command+=(.)

    "${docker_build_command[@]}"

    if (( $? != 0 )); then
        exit 1
    fi

    if [[ -n "${prefix}" ]]; then
        target="${prefix}${tag}"
        docker tag "${tag}" "${target}"
    fi
}

###########################################################################
# Build the Registry mod_auth_openidc image.
# Globals:
#   None
# Arguments:
#   Full image name prefix, a string.
#   Tag label, a string.
#   Tag suffix, a string.
#   Docker build flags, other flags for docker build.
# Outputs:
#   None
###########################################################################
function build_mod_auth_openidc() {
    local docker_build_command
    local docker_build_flags
    local label
    local prefix
    local suffix

    prefix="$1"
    label="$2"
    suffix="$3"

    if [[ -z "${label}" ]]; then
        err "ERROR:build_mod_auth_openidc: label cannot be empty"
        return 1
    fi

    if [[ -z "${suffix}" ]]; then
        err "ERROR:build_mod_auth_openidc: suffix cannot be empty"
        return 1
    fi

    declare -a docker_build_flags=("${@:4}")

    tag="comanage-registry:${label}-mod_auth_openidc-${suffix}"

    docker_build_command=(docker build)

    if ((${#docker_build_flags[@]})); then
        for flag in "${docker_build_flags[@]}"; do
            docker_build_command+=("${flag}")
        done
    fi

    docker_build_command+=(--tag "${tag}")
    docker_build_command+=(--build-arg COMANAGE_REGISTRY_VERSION="${label}")
    docker_build_command+=(--build-arg COMANAGE_REGISTRY_BASE_IMAGE_VERSION="${suffix}")
    docker_build_command+=(--file container/registry/mod_auth_openidc/Dockerfile)
    docker_build_command+=(.)

    "${docker_build_command[@]}"

    if (( $? != 0 )); then
        exit 1
    fi

    if [[ -n "${prefix}" ]]; then
        target="${prefix}${tag}"
        docker tag "${tag}" "${target}"
    fi
}

###########################################################################
# Echo errors to stderr with timestamp.
# Globals:
#   None
# Arguments:
#   None
# Outputs:
#   Writes errors to stderr.
###########################################################################
function err() {
    echo "[$(date +'%Y-%m-%dT%H:%M:%S%z')]: $*" >&2
}

###########################################################################
# Echo usage message to stdout.
# Globals:
#   None
# Arguments:
#   Array of all input parameters
# Outputs:
#   Writes usage message to stdout.
###########################################################################
function usage() {
    local usage

    read -d '' usage <<EOF
NAME
    $0 - build COmanage Registry container images

SYNOPSIS
    $0 -s|--suffix=SUFFIX [OPTION]... PRODUCT

DESCRIPTION
    Build COmanage Registry container images.

    PRODUCT is one of
        registry AUTHENTICATION

    where AUTHENTICATION is one of
        mod_auth_openidc

    The full name of the built images has the format

    REGISTRY/NAMESPACE/NAME:TAG

    When PRODUCT is registry NAME has the format

    comanage-registry

    and TAG has the format

    LABEL-AUTHENTICATION-SUFFIX

    If not specified LABEL is determined by inspecting
    the source tree and has the format

    GITHUB_TAG|GITHUB_BRANCH-COMMIT

    -h, --help
            show this usage message

    --build-arg
            pass build argument to docker build

    -l, --label
            label to use in image tag, default is determined
            by inspecting the source tree and has the format
            GITHUB_TAG for source tree tags or
            GITHUB_BRANCH-COMMIT when building from a branch

    --namespace
            image namespace, default is none,
            required if --registry is specified

    --no-cache
            passed to docker build if present

    --registry
            image registry, default is none

    --rm
            passed to docker build if present

    -s, --suffix
            required image tag suffix

EXAMPLES
    $0 -s 1 registry mod_auth_openidc
        Build the Registry image with OIDC authentication and tag suffix 1.

    $0 --suffix=mytag --no-cache registry mod_auth_openidc
        Build the Registry image with OIDC authentication and tag suffix
        mytag and pass --no-cache to the docker build command.
EOF

    echo "${usage}"
}

###########################################################################
# Use git to inspect repository state and return version string.
# Globals:
#   None
# Arguments:
#   None
# Outputs:
#   Writes version string to stdout.
###########################################################################
function label_from_repository() {
    local branch
    local label

    git symbolic-ref -q HEAD > /dev/null 2>&1
    if (( $? == 0 )); then
        branch="$(git rev-parse --abbrev-ref HEAD)"
        if [[ "${branch}" == "main" ]]; then
            label="$(git describe --tags --abbrev=0)"
        else
            label="${branch}-$(git rev-parse --short HEAD)"
        fi
    else
        label="$(git rev-parse --short HEAD)"
    fi

    echo "${label}"
}

###########################################################################
# Parse command line and execute as specified.
# Globals:
#   None
# Arguments:
#   Array of all input parameters
# Outputs:
#   None
###########################################################################
function main() {
    local authentication
    local docker_build_flags
    local gnu_getopt_out
    local label=""
    local namespace
    local prefix=""
    local product
    local registry
    local suffix

    # Require bash version 4 or higher.
    if [[ ! "${BASH_VERSINFO:-0}" -ge 4 ]]; then
        err "ERROR: Bash version must be 4 or greater"
        exit 1
    fi

    # Require getopt version 2.32 or greater.
    getopt_version=$(/usr/bin/getopt --version | cut -d' ' -f4 | cut -c1-4 | tr -d .)
    if [[ ! "${getopt_version:-0}" -ge 232 ]]; then
        err "ERROR: getopt version must be 2.32 or greater"
        exit 1
    fi

    declare -a docker_build_flags=()

    gnu_getopt_out=$(/usr/bin/getopt \
                     --options hl:s: \
                     --longoptions help \
                     --longoptions build-arg: \
                     --longoptions label: \
                     --longoptions namespace: \
                     --longoptions no-cache \
                     --longoptions registry: \
                     --longoptions rm \
                     --longoptions suffix: \
                     --name 'build.sh' -- "${@}")

    if [[ $? != 0 ]]; then
        err "ERROR: unable to parse command line"
        exit 1
    fi

    eval set -- "${gnu_getopt_out}"

    while true; do
        case "$1" in
            -h | --help ) usage $@; exit ;;
            --build-arg ) docker_build_flags+=(--build-arg "$2") ; shift 2 ;;
            -l | --label ) label="$2"; shift 2 ;;
            --namespace ) namespace="$2"; shift 2 ;;
            --no-cache ) docker_build_flags+=(--no-cache) ; shift 1 ;;
            --registry ) registry="$2"; shift 2 ;;
            --rm ) docker_build_flags+=(--rm) ; shift 1 ;;
            -s | --suffix ) suffix="$2"; shift 2 ;;
            -- ) shift; break ;;
            * ) break ;;
        esac
    done

    if [[ -z "${suffix}" ]]; then
        err "ERROR: --suffix must be specified"
        exit 1
    fi

    if [[ -z "${namespace}" && -n "${registry}" ]]; then
        err "ERROR: --namespace must be specified if --registry is specified"
        exit 1
    fi

    if [[ -z "${label}" ]]; then
        label="$(label_from_repository)"
    fi

    if [[ -n "${namespace}" ]]; then
        prefix="${namespace}/"
        if [[ -n "${registry}" ]]; then
            prefix="${registry}/${prefix}"
        fi
    fi

    product="$1"

    case "${product}" in
        registry )
            authentication="$2"
            case "${authentication}" in
                mod_auth_openidc )
                    build_base "${prefix}" "${label}" "${suffix}" "${docker_build_flags[@]}" \
                    && build_mod_auth_openidc "${prefix}" "${label}" "${suffix}" "${docker_build_flags[@]}"
                    ;;
                *)
                    err "ERROR: Unrecognized authentication"
                    echo
                    usage
                    ;;
            esac
            ;;
        *)
            err "ERROR: unrecogized product"
            echo
            usage
            ;;
    esac
}

main "$@"
