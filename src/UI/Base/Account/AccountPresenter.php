<?php declare(strict_types=1);

namespace UI\Base\Account;

use UI\Base\BasePresenter;
use Core\Container;
use Core\FormFactory;
use Models\Customer\CustomerAuthService;
use Models\Customer\CustomerRepository;
use Models\Customer\DeliveryAddressRepository;
use Nette\Forms\Form;

class AccountPresenter extends BasePresenter
{
    public function __construct(
        Container $container,
        protected CustomerAuthService $customerAuthService,
        protected CustomerRepository $customerRepository,
        protected DeliveryAddressRepository $deliveryAddressRepository,
        protected \Core\Email\EmailService $emailService,
    ) {
        parent::__construct($container);
    }

    /**
     * Profile page - requires login
     */
    public function actionProfile(): void
    {
        $this->requireLogin();
    }

    /**
     * Render profile page
     */
    public function renderProfile(): void
    {
        $this->assign('pageTitle', 'Můj profil');
        $this->render();
    }

    public function actionEdit(): void
    {
        $this->requireLogin();

        // Pre-fill form with current customer data
        $customer = $this->getCustomer();
        if ($customer && !$this->getComponent('profileForm')->isSubmitted()) {
            $this->getComponent('profileForm')->setDefaults([
                'email' => $customer->email,
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'phone' => $customer->phone ?? '',
                'companyName' => $customer->companyName ?? '',
            ]);
        }
    }

    /**
     * Render edit profile page
     */
    public function renderEdit(): void
    {
        $this->assign('pageTitle', 'Upravit profil');
        $this->render();
    }

    /**
     * Change password page - requires login
     */
    public function actionChangePassword(): void
    {
        $this->requireLogin();
    }

    /**
     * Render change password page
     */
    public function renderChangePassword(): void
    {
        $this->assign('pageTitle', 'Změnit heslo');
        $this->render();
    }

    /**
     * Delivery addresses page - requires login
     */
    public function actionAddresses(): void
    {
        $this->requireLogin();

        $customer = $this->getCustomer();
        $addresses = $this->deliveryAddressRepository->findByCustomerId($customer->id);

        $this->assign('addresses', $addresses);
    }

    /**
     * Render delivery addresses page
     */
    public function renderAddresses(): void
    {
        $this->assign('pageTitle', 'Doručovací adresy');
        $this->render();
    }

    /**
     * Edit address action - requires login
     */
    public function actionEditAddress(int $id): void
    {
        $this->requireLogin();

        $address = $this->deliveryAddressRepository->findById($id);

        // Check if address belongs to current customer
        $customer = $this->getCustomer();
        if (!$address || $address->customerId !== $customer->id) {
            $this->flashMessage('Adresa nebyla nalezena', 'danger');
            $this->redirect('Account:addresses');
        }

        $this->assign('editAddress', $address);

        // Pre-fill form with address data
        if (!$this->getComponent('addressForm')->isSubmitted()) {
            $this->getComponent('addressForm')->setDefaults([
                'name' => $address->name,
                'companyName' => $address->companyName,
                'firstName' => $address->firstName,
                'street' => $address->street,
                'city' => $address->city,
                'postalCode' => $address->postalCode,
                'country' => $address->country,
                'phone' => $address->phone,
                'courierNote' => $address->courierNote,
                'isDefault' => $address->isDefault,
            ]);
        }
    }

    /**
     * Render edit address page
     */
    public function renderEditAddress(): void
    {
        $this->assign('pageTitle', 'Upravit adresu');
        $this->render();
    }

    /**
     * Handle setting address as default
     */
    public function actionSetDefaultAddress(int $id): void
    {
        $this->requireLogin();

        $address = $this->deliveryAddressRepository->findById($id);
        $customer = $this->getCustomer();

        if (!$address || $address->customerId !== $customer->id) {
            $this->flashMessage('Adresa nebyla nalezena', 'danger');
        } else {
            $this->deliveryAddressRepository->setAsDefault($id);
            $this->flashMessage('Výchozí adresa byla nastavena', 'success');
        }

        $this->redirect('Account:addresses');
    }

    /**
     * Handle deleting address
     */
    public function actionDeleteAddress(int $id): void
    {
        $this->requireLogin();

        $address = $this->deliveryAddressRepository->findById($id);
        $customer = $this->getCustomer();

        if (!$address || $address->customerId !== $customer->id) {
            $this->flashMessage('Adresa nebyla nalezena', 'danger');
        } else {
            $this->deliveryAddressRepository->delete($id);
            $this->flashMessage('Adresa byla smazána', 'success');
        }

        $this->redirect('this');
    }

    /**
     * Create profile edit form component
     */
    protected function createComponentProfileForm(): Form
    {
        $form = FormFactory::create();

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail');

        $form->addText('firstName', 'Jméno:')
            ->setRequired('Vyplňte jméno');

        $form->addText('lastName', 'Příjmení:')
            ->setRequired('Vyplňte příjmení');

        $form->addText('phone', 'Telefon:');

        $form->addText('companyName', 'Název firmy:');

        $form->addSubmit('submit', 'Uložit změny');

        $form->onSuccess[] = [$this, 'profileFormSucceeded'];

        return $form;
    }

    /**
     * Process profile edit form
     */
    public function profileFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $customer = $this->getCustomer();

            // Validate email uniqueness if changed
            if ($values->email !== $customer->email) {
                if ($this->customerRepository->emailExistsForAnotherCustomer($values->email, $customer->id)) {
                    $form->addError('Tento email již používá jiný zákazník');
                    return;
                }
            }

            // Update customer in database
            $this->customerRepository->update($customer->id, [
                'email' => $values->email,
                'firstName' => $values->firstName,
                'lastName' => $values->lastName,
                'phone' => $values->phone,
                'companyName' => $values->companyName,
            ]);

            // Update session data (firstName, lastName changed)
            $_SESSION['customer']['firstName'] = $values->firstName;
            $_SESSION['customer']['lastName'] = $values->lastName;

            $this->flashMessage('Profil byl úspěšně aktualizován', 'success');
            $this->redirect('Account:profile');

        } catch (\Exception $e) {
            $form->addError($e->getMessage());
        }
    }

    /**
     * Create change password form component
     */
    protected function createComponentPasswordForm(): Form
    {
        $form = FormFactory::create();

        $form->addPassword('currentPassword', 'Současné heslo:')
            ->setRequired('Vyplňte současné heslo');

        $form->addPassword('newPassword', 'Nové heslo:')
            ->setRequired('Vyplňte nové heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('newPasswordVerify', 'Nové heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['newPassword']);

        $form->addSubmit('submit', 'Změnit heslo');

        $form->onSuccess[] = [$this, 'passwordFormSucceeded'];

        return $form;
    }

    /**
     * Process change password form
     */
    public function passwordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $customer = $this->getCustomer();

            // Verify current password
            if (!$customer->verifyPassword($values->currentPassword)) {
                $this->flashMessage('Současné heslo není správné', 'danger');
                return;
            }

            // Update password
            $this->customerRepository->updatePassword($customer->id, $values->newPassword);

            $this->flashMessage('Heslo bylo úspěšně změněno', 'success');
            $this->redirect('Account:profile');

        } catch (\Exception $e) {
            $form->addError($e->getMessage());
        }
    }

    /**
     * Create address form component
     */
    protected function createComponentAddressForm(): Form
    {
        $form = FormFactory::create();

        $form->addText('name', 'Název adresy:')
            ->setRequired('Vyplňte název (např. Domů, Do práce...)')
            ->setHtmlAttribute('placeholder', 'Domů, Do práce...');

        $form->addText('companyName', 'Název firmy:');

        $form->addText('firstName', 'Jméno a příjmení:')
            ->setRequired('Vyplňte jméno');

        $form->addText('street', 'Ulice a číslo:')
            ->setRequired('Vyplňte ulici');

        $form->addText('city', 'Město:')
            ->setRequired('Vyplňte město');

        $form->addText('postalCode', 'PSČ:')
            ->setRequired('Vyplňte PSČ')
            ->addRule(Form::Pattern, 'PSČ musí být ve formátu XXX XX', '[0-9]{3}\s?[0-9]{2}');

        $form->addText('phone', 'Telefon:')
            ->setHtmlAttribute('placeholder', '123 456 789');

        $form->addTextArea('courierNote', 'Poznámka pro kurýra:')
            ->setHtmlAttribute('rows', 2)
            ->setHtmlAttribute('placeholder', 'Např. zvonek u vrat, 2. patro...');

        $form->addCheckbox('isDefault', 'Nastavit jako výchozí adresu');

        $form->addSubmit('submit', 'Uložit adresu');

        $form->onSuccess[] = [$this, 'addressFormSucceeded'];

        return $form;
    }

    /**
     * Process address form
     */
    public function addressFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $customer = $this->getCustomer();

            // Check if we're editing existing address
            $addressId = $this->params['id'] ?? null;

            if ($addressId) {
                // Edit existing address
                $address = $this->deliveryAddressRepository->findById((int)$addressId);

                if (!$address || $address->customerId !== $customer->id) {
                    $this->flashMessage('Adresa nebyla nalezena', 'danger');
                    $this->redirect('Account:addresses');
                }

                $this->deliveryAddressRepository->update((int)$addressId, [
                    'name' => $values->name,
                    'companyName' => $values->companyName,
                    'firstName' => $values->firstName,
                    'street' => $values->street,
                    'city' => $values->city,
                    'postalCode' => $values->postalCode,
                    'country' => $values->country,
                    'phonePrefix' => $values->phonePrefix,
                    'phone' => $values->phone,
                    'courierNote' => $values->courierNote,
                    'openingHours' => $values->openingHours,
                    'isDefault' => $values->isDefault,
                ]);

                $this->flashMessage('Adresa byla úspěšně upravena', 'success');
            } else {
                // Create new address
                $this->deliveryAddressRepository->create([
                    'customerId' => $customer->id,
                    'name' => $values->name,
                    'companyName' => $values->companyName,
                    'firstName' => $values->firstName,
                    'street' => $values->street,
                    'city' => $values->city,
                    'postalCode' => $values->postalCode,
                    'country' => $values->country,
                    'phonePrefix' => $values->phonePrefix,
                    'phone' => $values->phone,
                    'courierNote' => $values->courierNote,
                    'openingHours' => $values->openingHours,
                    'isDefault' => $values->isDefault,
                ]);

                $this->flashMessage('Adresa byla úspěšně přidána', 'success');
            }

            $this->redirect('Account:addresses');

        } catch (\Exception $e) {
            $form->addError($e->getMessage());
        }
    }




    public function actionTestEmail(): void
    {
        // Create test SMTP email
        $message = new \Core\Email\EmailMessage(
            to: 'test@example.com',
            subject: 'Test SMTP Email z Florea.cz',
            htmlBody: '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #007bff; color: white; padding: 20px; }
                    .content { padding: 20px; border: 1px solid #ddd; }
                    .footer { padding: 10px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Test SMTP Email</h1>
                    </div>
                    <div class="content">
                        <h2>Email systém funguje! ✅</h2>
                        <p>Tento email byl odeslán z <strong>Florea.cz</strong> redesignu.</p>
                        <p>Použitý provider: <strong>NetteMailProvider (SMTP)</strong></p>
                        <p>Server: <strong>MailHog (localhost:1025)</strong></p>
                        <hr>
                        <p>Čas odeslání: <strong>' . date('d.m.Y H:i:s') . '</strong></p>
                        <p>Shop: <strong>xxx</strong></p>
                    </div>
                    <div class="footer">
                        Toto je testovací email z email integrace.
                    </div>
                </div>
            </body>
            </html>
        ',
            textBody: 'Test SMTP Email - Email systém funguje! Čas: ' . date('d.m.Y H:i:s')
        );

        try {
            // Send email
            $this->emailService->send($message);

            $this->flashMessage('✅ Email byl úspěšně odeslán! Zkontroluj MailHog na http://localhost:8025', 'success');

        } catch (\Exception $e) {
            $this->flashMessage('❌ Chyba při odesílání: ' . $e->getMessage(), 'danger');
        }

        // Redirect back to profile
        $this->redirect('Account:profile');
    }

    public function actionTestMaileon(): void
    {
        // Load email config
        $emailConfig = \Core\Config::get('email');

        // Get current customer (if logged in) or use dummy data
        $customer = $this->getCustomer();

        if ($customer) {
            $recipientEmail = $customer->email;
        } else {
            // Not logged in - use test data
            $recipientEmail = 'ahoj@michal-marek.cz';  // ← Změň na svůj testovací email!
        }
        $recipientEmail = 'ahoj@michal-marek.cz';  // ← Změň na svůj testovací email!

        // Get Event ID from config
        $eventId = $emailConfig['maileon']['events']['passwordReset'];

        // Create Maileon email
        $message = new \Core\Email\EmailMessage(
            to: $recipientEmail,
            subject: 'Test Maileon Email - Password Reset',
            maileonEventId: $eventId,
            personalizedData: [
                'reset_url' => "url",
                'shopID' => 2,
                'lang' => 'cs',
            ]
        );

        try {
            // Send email
            $this->emailService->send($message);

            $this->flashMessage(
                "✅ Maileon email byl úspěšně odeslán na {$recipientEmail}! " .
                "Zkontroluj svou mailovou schránku.",
                'success'
            );

        } catch (\Exception $e) {
            $this->flashMessage(
                '❌ Chyba při odesílání Maileon emailu: ' . $e->getMessage(),
                'danger'
            );

            // Log error for debugging
            error_log('[Maileon Test Error] ' . $e->getMessage());
        }

        // Redirect back to profile
        $this->redirect('Account:profile');
    }





}