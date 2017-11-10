@setup
    require __DIR__.'/vendor/autoload.php';
    $dotenv = new Dotenv\Dotenv(__DIR__);
    try {
        $dotenv->load();
        $dotenv->required(['DEPLOY_SERVER', 'DEPLOY_REPOSITORY', 'DEPLOY_PATH'])->notEmpty();
    } catch ( Exception $e )  {
        echo $e->getMessage();
    }

    $server = getenv('DEPLOY_SERVER');
    $repo = getenv('DEPLOY_REPOSITORY');
    $path = getenv('DEPLOY_PATH');

    if ( substr($path, 0, 1) !== '/' ) throw new Exception('Careful - your deployment path does not begin with /');

    $date = ( new DateTime )->format('Y-m-d-H-i-s');
    $env = isset($env) ? $env : "production";
    $branch = isset($branch) ? $branch : "master";
    $path = rtrim($path, '/');
    $release = $path.'/.release_'.$date;
    $archive = rtrim($path.'/.archive');
@endsetup

@servers(['web' => $server])

@task('init')
    if [ ! -d {{ $path }}/current ]; then
        cd {{ $path }}
        git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
        printf "\e[92mRepository cloned\n";
        mv {{ $release }}/storage {{ $path }}/storage
        ln -s {{ $path }}/storage {{ $release }}/storage
        ln -s {{ $path }}/storage/public {{ $release }}/public/storage
        printf "\e[92mStorage directory set up\n";
        if [ ! -f {{ $path }}/.env ]; then
            if [ ! -f {{ $release }}/.env.deploy ]; then
                cp {{ $release }}/.env.example {{ $path }}/.env;
            else
                cp {{ $release }}/.env.deploy {{ $path }}/.env;
            fi
        fi
        ln -s {{ $path }}/.env {{ $release }}/.env;
        cd {{ $release }};
        composer install --no-interaction --quiet --no-dev --no-scripts --optimize-autoloader;
        php artisan key:generate;
        printf "\e[92mEnvironment file set up\n";
        rm -rf {{ $release }}
        printf "\e[92mDeployment path initialised.\n";
        printf "\e[96mRun '\e[91menvoy run deploy\e[96m [\e[93m--branch=BRANCH\e[96m] [\e[93m--env=ENV\e[96m]' now.\n";
    else
        printf "\e[91mDeployment path already initialised (current symlink exists)!\n";
    fi
@endtask

@story('deploy')
    task_start
    task_links
    task_composer
    task_migrate
    task_cache
    task_optimize
    task_finish
    task_option_cleanup
@endstory

@story('rollback')
    task_rollback
    task_cache
    task_optimize
@endstory

@task('task_rollback')
    cd {{ $path }};
    ARCHIVE={{ $archive }};
    mkdir -p $ARCHIVE;
    if [ "$(ls -A $ARCHIVE)" ]; then
        LAST_RELEASE=$(ls -A $ARCHIVE | sort -nr | head -1);
        rm -rf $(readlink current);
        mv {{ $archive }}/$LAST_RELEASE {{ $path }}/;
        ln -nfs $LAST_RELEASE ./current;
        printf "\e[92mRollback to ${LAST_RELEASE} - success!\n";
    else
        printf "\e[91mThere were no releases before!\n";
    fi
@endtask

@task('task_start')
    printf "\e[96mDeployment ({{ $date }}) started\n";
    git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }};
    printf "\e[92mRepository cloned\n";
@endtask

@task('task_links')
    cd {{ $path }};
    rm -rf {{ $release }}/storage;
    ln -s {{ $path }}/storage {{ $release }}/storage;
    ln -s {{ $path }}/storage/app/public {{ $release }}/public/storage;
    printf "\e[92mStorage directories set up\n";
    ln -s {{ $path }}/.env {{ $release }}/.env;
    printf "\e[92mEnvironment file set up\n";
@endtask

@task('task_composer')
    cd {{ $release }};
    composer install --no-interaction --quiet --no-dev --no-scripts --optimize-autoloader;
@endtask

@task('task_migrate')
    if [ ! -f {{ $release }}/artisan ]; then
        WORK_DIR="{{ $path }}/current";
    else
        WORK_DIR="{{ $release }}";
    fi
    php ${WORK_DIR}/artisan migrate --env={{ $env }} --force --no-interaction
@endtask

@task('task_cache')
    if [ ! -f {{ $release }}/artisan ]; then
        WORK_DIR="{{ $path }}/current";
    else
        WORK_DIR="{{ $release }}";
    fi
    php ${WORK_DIR}/artisan view:clear --quiet;
    php ${WORK_DIR}/artisan cache:clear --quiet;
    php ${WORK_DIR}/artisan config:cache --quiet;
    printf "\e[92mCache cleared\n";
@endtask

@task('task_optimize')
    if [ ! -f {{ $release }}/artisan ]; then
        WORK_DIR="{{ $path }}/current";
    else
        WORK_DIR="{{ $release }}";
    fi
    php ${WORK_DIR}/artisan optimize --quiet
@endtask

@task('task_finish')
    mkdir -p {{ $archive }};
    if [ -d {{ $path }}/current ]; then
        mv $(readlink {{ $path }}/current) {{ $archive }}/$(basename $(readlink {{ $path }}/current));
    fi
    ln -nfs {{ $release }} {{ $path }}/current
    printf "\e[96mDeployment ({{ $date }}) finished\n";
@endtask

@task('task_option_cleanup')
    cd {{ $path }};
    @if ( isset($cleanup) && $cleanup )
        find . -maxdepth 1 -name "20*" -mmin +2880 | xargs rm -rf;
        printf "\e[92mCleaned up old deployments\n";
    @endif
@endtask