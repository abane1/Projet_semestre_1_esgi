<?php

namespace App\Controllers;

use App\Controllers\Error;
use App\Core\View;
use App\Forms\UserInsert;
use App\Forms\UserLogin;
use App\Forms\PwdForget;
use App\Forms\ModifieAccount;
use App\Core\Verificator;
use App\Models\PhpMailor;
use App\Models\User;

class Security
{
    public function UserIsLogged(): bool {
        return isset($_SESSION['Connected']) && $_SESSION['Connected'] == true;
    }


    public function login(): void
    {
        if ($this->UserIsLogged()){
            header("Location: /needtologout");
            exit;
        }

        $form = new UserLogin();
        $view = new View("Security/login", "front");
        $view->assign('config', $form->getConfig());
        if ($form->isSubmit() && $form->isValid()){
            $user = new User();
            $emailverification = $user->getOneBy(["email" => strtolower($_POST['user_email'])], "object");
            if ($emailverification == true)
            {
                if (password_verify($_POST['user_password'], $emailverification->getPassword())) {
                    $accountemailverification = $user->getOneBy(["email_verified" => 1], "object");
                    if($accountemailverification) {
                        $_SESSION['Connected'] = true;
                        header("Location: /");
                        exit;
                    }
                    else
                    {
                        $errors['user_email'] = "Un mail d'activation vous a été envoyé lors de la création de votre compte.<br>Merci de confirmer votre adresse e-mail.";
                        $view->assign('errors', $errors);
                        exit;
                    }
                }
                else
                {
                    $errors['user_email'] = "Le mot de passe est incorrecte.";
                    $view->assign('errors', $errors);
                    exit;
                }
            }
            else
            {
                $errors['user_email'] = "L'adresse e-mail que vous avez saisie ne correspond à aucun compte sur notre site.";
                $view->assign('errors', $errors);
                exit;
            }
        }
        else{
            $view->assign('errors', $form->listOfErrors);
        }
    }


    public function logout(): void
    {
        session_destroy();
        header("Location: /login");
        exit;
    }

    /**
     * This code handles the login functionality and user registration.
     * If the user is already logged in, it redirects to the dashboard.
     * It creates a new UserInsert form and assigns it to the view.
     * If the form is submitted, it validates the form data and performs user registration if there are no errors.
     * If the email already exists, it displays an error message.
     * After successful registration, it generates a verification token, saves the user data, and redirects to the login page.
     * If there are any errors in the form data, it assigns the errors to the view.
     */

    public function register(): void
    {
        if ($this->UserIsLogged()){
            header("Location: /needtologout");
            exit;
        }

        $form = new UserInsert();
        $view = new View("Security/register", "front");
        $view->assign('config', $form->getConfig());

        if ($form->isSubmit() && $form->isValid()){
            $user = new User();

            $user = new User();
            $emailverification = $user->getOneBy(["email" => $_POST['user_email']], "object");
            if ($emailverification == true)
            {
                $errors['user_email'] = "L'adresse e-mail est déjà utilisée. Merci de bien vouloir renseigner une autre adresse e-mail.";
                $view->assign('errors', $errors);
                exit;
            }else{
                $token = bin2hex(random_bytes(32));
                $user->setVericationToken($token);
                $user->setFirstname($_POST['user_firstname']);
                $user->setLastname($_POST['user_lastname']);
                $user->setEmail($_POST['user_email']);
                $user->setPassword($_POST['user_password']);
                $user->save();

                $phpMailer = new PhpMailor();
                $phpMailer->sendMail($_POST['user_email'], $_POST['user_firstname'], $_POST['user_lastname'], $token, "Verification");
                header("Location: " . '/email-verification');
                exit;
            }
        }else{
            $view->assign('errors', $form->listOfErrors);
        }

    }



    public function pwdForget(): void
    {
        if ($this->UserIsLogged()){
            header("Location: /");
            exit;
        }

        $form = new PwdForget();
        $view = new View("Security/forgetpwd", "front");
        $view->assign('config', $form->getConfig());

        if ($form->isSubmit()){
            $user = new User();
        }else{
            $view->assign('errors', $form->listOfErrors);
        }
    }

    public function modifieAccount(): void
    {
        $form = new ModifieAccount();
        $config = $form->getConfig();
        $errors = [];
        $myView = new View("Security/accountInfo", "front");
        $myView->assign("configForm", $config);
        $myView->assign("errorsForm", $errors);
        echo "Ma page de modification cu compte";
    }

    public function confirmedEmail(): void
    {
        $user = new User();
        $userverified = $user->getOneBy(["email_verified" => 1], "object");
        //Si le compte est ps connected et vefifier son email_verified à 1
        if ($userverified == true && $this->UserIsLogged() == false) {
            $myView = new View("Security/emailconfirmed", "front");
        }
        else
        {
            die("Page 404");
            $customError = new Error();
            $customError->page404();
        }
    }

    public function verifyEmailNotify(): void
    {
        $user = new User();
        $userverified = $user->getOneBy(["email_verified" => 1], "object");
        //Si le compte est ps connected et vefifier son email_verified à 1
        if (!$userverified && $this->UserIsLogged() == false) {
            $myView = new View("Security/emailconfirmedmsg", "front");
        }
        else
        {
            die("Page 404");
            $customError = new Error();
            $customError->page404();
        }
    }

    public function needToLogout()
    {
        //Si le compte est ps connected et vefifier son email_verified à 1
        if ($this->UserIsLogged() == true) {
            $myView = new View("Security/needtologout", "front");
        }
        else
        {
            die("Page 404");
            $customError = new Error();
            $customError->page404();
        }
    }

    public function verifyEmail()
    {
        if (!isset($_GET['token']) || empty($_GET['token'])) {
            die("Page 404");
            $customError = new Error();
            $customError->page404();
        } else {
            $token = $_GET['token'];
            $user = new User();
            $userverified = $user->getOneBy(["verification_token" => $token], "object");
            if (!$userverified) {
                die("Page 404");
                $customError = new Error();
                $customError->page404();
            } else {

                $userverified->setEmailVerified(1);
                $userverified->save();
                header("Location: " . '/email-confirmed');
                exit;
            }
        }

    }
}