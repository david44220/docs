<?php
/**
 * Web installer. Runs once: afterwards storage/installed.lock blocks it.
 * You may delete this file after installation.
 */
define('INSTALLER', true);
require __DIR__ . '/../app/bootstrap.php';
require APP_DIR . '/lib/installer.php';

if (is_installed()) {
    abort(403, t('The site is already installed. To run the installer again, delete storage/installed.lock first.'));
}

$requirements = install_requirements();
$ready = !in_array(false, $requirements, true);
$error = null;
$form = [
    'db_host'        => '127.0.0.1',
    'db_port'        => '3306',
    'db_name'        => '',
    'db_user'        => '',
    'site_name'      => 'Bubble Cycler',
    'base_url'       => rtrim(absolute_url(''), '/'),
    'admin_username' => 'admin',
    'admin_email'    => '',
];

if (is_post() && $ready) {
    foreach (array_keys($form) as $key) {
        $form[$key] = post($key);
    }
    $dbPass = is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '';
    $adminPass = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
    try {
        if (!preg_match('/^\d{2,5}$/', $form['db_port'])) {
            throw new AppError(t('Database port must be a number, usually 3306.'));
        }
        if ($form['db_name'] === '' || $form['db_user'] === '') {
            throw new AppError(t('Enter the database name and user.'));
        }
        $created = install_run(
            ['host' => $form['db_host'], 'port' => (int) $form['db_port'], 'name' => $form['db_name'], 'user' => $form['db_user'], 'pass' => $dbPass],
            ['site_name' => $form['site_name'], 'base_url' => $form['base_url']],
            ['username' => $form['admin_username'], 'email' => $form['admin_email'], 'password' => $adminPass]
        );
        echo view_capture('install/done', ['adminCreated' => $created, 'form' => $form]);
        exit;
    } catch (AppError $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = t('Database error: {message}', ['message' => $e->getMessage()]);
    }
}

echo view_capture('install/form', [
    'requirements' => $requirements,
    'ready'        => $ready,
    'error'        => $error,
    'form'         => $form,
]);
