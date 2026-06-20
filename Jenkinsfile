pipeline {
agent any

```
environment {
    DEPLOY_DIR = "/home/alvin/apps/biji/laravel"
    REPO_URL = "https://github.com/veryepiccindeed/biji-kopi.git"
    BRANCH = "master"
}

stages {

    stage('Prepare') {
        steps {
            sh '''
            mkdir -p /home/alvin/apps/biji/laravel
            '''
        }
    }

    stage('Update Source') {
        steps {
            withCredentials([
                usernamePassword(
                    credentialsId: 'github-token',
                    usernameVariable: 'GIT_USER',
                    passwordVariable: 'GIT_TOKEN'
                )
            ]) {
                sh '''
                if [ ! -d "${DEPLOY_DIR}/.git" ]; then

                    git clone \
                    -b ${BRANCH} \
                    https://${GIT_USER}:${GIT_TOKEN}@github.com/veryepiccindeed/biji-kopi.git \
                    ${DEPLOY_DIR}

                else

                    cd ${DEPLOY_DIR}

                    git fetch origin

                    git reset --hard origin/${BRANCH}

                fi
                '''
            }
        }
    }

    stage('Deploy') {
        steps {
            sh '''
            cd /home/alvin/apps/biji

            docker compose down

            docker compose up -d --build
            '''
        }
    }
}
```

}
