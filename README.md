# Thawani Payment Gateway Integration for Perfex CRM

This is a custom module that integrates the **Thawani Payment Gateway** with **Perfex CRM** for secure online payments.

## 🚀 Features

- Accept payments using Thawani's secure checkout.
- Automatically update invoice status on payment success.
- Seamless integration with Perfex CRM's invoice and payment system.
- Easy configuration and activation from the admin panel.

## 📦 Installation

1. **Download the module**  
   Clone or download this repository to your local machine.

2. **Upload the module**  
   Upload the contents of the `thawanipay` folder to:

3. **Activate the module**
- Go to **Setup → Modules** in the Perfex CRM admin panel.
- Find **Thawani Pay** and click **Activate**.

4. **Configure the gateway**
- Go to **Setup → Settings → Payment Gateways → Thawani Pay**.
- Enter your:
  - **API Key**
  - **Publishable Key**
  - **Mode** (Live or Test)
- Save your changes.


## 🚧 Upcoming Features

- **Refund from admin panel** *(Coming Soon)*
- Detailed transaction logs
- Better error logging and debugging

## 📚 References

- [Thawani API Documentation](https://docs.thawani.om/)
- [Perfex CRM Official Site](https://www.perfexcrm.com/)
- phpawcom/thawani-php (Composer Package) – Used for interacting with the Thawani API.

Note: This module includes the phpawcom/thawani-php package under MIT license.

## ✅ Requirements

- Perfex CRM 3.x+
- PHP 7.4+
- cURL enabled

## 🧩 License

This module is provided as-is for educational and integration purposes. Use at your own discretion.

---

**Developed by**: House of Servers, by Elite Tech Marketing Solutions LLC
**Contact**: info@serverhouseoman.com
