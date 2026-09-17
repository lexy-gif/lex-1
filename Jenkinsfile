pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
        disableConcurrentBuilds()
        timeout(time: 30, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '20'))
        timestamps()
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Check Tools') {
            steps {
                script {
                    if (isUnix()) {
                        error('This pipeline requires a Windows Jenkins agent.')
                    }
                }
                bat '@git --version'
                bat '@node --version'
                bat '@call npm.cmd --version'
                bat '@docker --version'
                script {
                    def dockerOS = bat(
                        returnStdout: true,
                        script: '@docker info --format "{{.OSType}}"'
                    ).trim()
                    if (dockerOS != 'linux') {
                        error('Docker must be running using the Linux container engine.')
                    }
                }
            }
        }

        stage('Install Frontend Dependencies') {
            steps {
                bat '@call npm.cmd ci --include=dev --engine-strict'
            }
        }

        stage('Frontend Tests') {
            steps {
                bat '@call npm.cmd run test:build'
            }
        }

        stage('Frontend Build') {
            steps {
                bat '@call npm.cmd run build'
            }
        }

        stage('Docker Build') {
            steps {
                script {
                    env.SRMS_CI_IMAGE = "srms-ci:${env.BUILD_NUMBER}"
                    env.DOCKER_IMAGE = "alexistechiz/srms:${env.BUILD_NUMBER}"
                }
                bat '@docker build --tag "%SRMS_CI_IMAGE%" .'
            }
        }

        stage('Check PHP') {
            steps {
                bat '@docker run --rm --entrypoint php "%SRMS_CI_IMAGE%" --version'
                bat '@docker run --rm --entrypoint php "%SRMS_CI_IMAGE%" -r "exit(extension_loaded(\'pdo_mysql\') ? 0 : 1);"'
            }
        }

        stage('PHPUnit Tests') {
            steps {
                bat '@docker run --rm --entrypoint php "%SRMS_CI_IMAGE%" vendor/bin/phpunit tests'
            }
        }

        stage('Docker Login') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: 'dockerhub-credentials',
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_TOKEN'
                    )
                ]) {
                    // Pass the token through stdin without expanding it in cmd.exe.
                    bat '''
                        @echo off
                        node -e "const cp = require('node:child_process'); const result = cp.spawnSync('docker', ['login', '--username', process.env.DOCKER_USER, '--password-stdin'], { input: process.env.DOCKER_TOKEN, stdio: ['pipe', 'inherit', 'inherit'] }); if (result.error) { console.error('Unable to start Docker login.'); process.exit(1); } process.exit(result.status === null ? 1 : result.status);"
                    '''
                }
            }
        }

        stage('Docker Tag') {
            steps {
                bat '@docker tag "%SRMS_CI_IMAGE%" "%DOCKER_IMAGE%"'
            }
        }

        stage('Docker Push') {
            steps {
                bat '@docker push "%DOCKER_IMAGE%"'
                echo "Successfully pushed: ${env.DOCKER_IMAGE}"
            }
        }
    }

    post {
        success {
            echo '================================'
            echo 'SRMS CI pipeline succeeded'
            echo "Docker image: ${env.DOCKER_IMAGE}"
            echo '================================'
        }

        failure {
            echo '================================'
            echo 'SRMS CI pipeline failed'
            echo 'Docker image was not successfully published.'
            echo '================================'
        }

        always {
            script {
                if (!isUnix()) {
                    def logoutStatus = bat(
                        returnStatus: true,
                        script: '@docker logout'
                    )
                    if (logoutStatus != 0) {
                        echo 'Docker logout failed; check the agent credential store.'
                    }
                }
            }
        }
    }
}
