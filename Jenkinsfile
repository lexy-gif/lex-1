pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
        disableConcurrentBuilds()
        timeout(time: 30, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '20'))
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Check tools') {
            steps {
                script {
                    if (isUnix()) {
                        error('This pipeline requires a Windows agent with Node 24 and Docker Linux containers.')
                    }
                    def dockerOS = bat(
                        returnStdout: true,
                        script: '@docker info --format "{{.OSType}}"'
                    ).trim()
                    if (dockerOS != 'linux') {
                        error('Start Docker with the Linux container engine before running this job.')
                    }
                }
                bat '@node --version'
                bat '@call npm.cmd --version'
            }
        }

        stage('Install frontend dependencies') {
            steps {
                bat '@call npm.cmd ci --include=dev --engine-strict'
            }
        }

        stage('Test frontend build') {
            steps {
                bat '@call npm.cmd run test:build'
            }
        }

        stage('Build frontend assets') {
            steps {
                bat '@call npm.cmd run build'
            }
        }

        stage('Build Docker image') {
            steps {
                script {
                    def revision = bat(returnStdout: true, script: '@git rev-parse --short=12 HEAD').trim()
                    env.SRMS_CI_IMAGE = "srms-ci:build-${env.BUILD_NUMBER}-${revision}"
                }
                bat '@docker build --tag "%SRMS_CI_IMAGE%" .'
            }
        }

        stage('Check PHP image') {
            steps {
                bat '@docker run --rm --entrypoint php "%SRMS_CI_IMAGE%" --version'
                bat '@docker run --rm --entrypoint php "%SRMS_CI_IMAGE%" -r "exit(extension_loaded(\'pdo_mysql\') ? 0 : 1);"'
                writeFile file: 'test-artifacts/ci-image.txt', text: "${env.SRMS_CI_IMAGE}\n"
                archiveArtifacts artifacts: 'test-artifacts/ci-image.txt', fingerprint: true
                echo "Built and checked ${env.SRMS_CI_IMAGE}"
            }
        }
    }
}
