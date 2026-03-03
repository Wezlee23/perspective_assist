# Perspective Assist (PetraAI)

Perspective Assist is a premium, AI-driven personal assistant application built using the latest Laravel ecosystem. It leverages NativePHP to provide a native desktop and mobile experience, combined with the dynamic power of Livewire 4 and Flux UI.

## 🚀 Features

- **Customizable Personas**: Define and interact with different AI personalities tailored to your needs.
- **Deep Chat History**: Persistent conversations with structured message history and metadata.
- **AI Settings**: Granular control over AI model parameters and provider configurations.
- **Native Experience**: Powered by NativePHP for a seamless desktop integration.
- **Modern UI**: A stunning, responsive interface built with Flux UI and Livewire.

## 🛠 Tech Stack

- **Framework**: [Laravel 12](https://laravel.com)
- **Frontend**: [Livewire 4](https://livewire.laravel.com), [Flux UI](https://fluxui.dev)
- **Desktop/Mobile**: [NativePHP](https://nativephp.com)
- **Database**: SQLite (default for local development)
- **AI Integration**: Custom services for LLM communication.

## 📦 Installation

To get started with development, follow these steps:

1. **Clone the repository**:
   ```bash
   git clone https://github.com/Wezlee23/perspective_assist.git
   cd perspective_assist
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Setup environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run migrations**:
   ```bash
   php artisan migrate
   ```

5. **Start development**:
   ```bash
   npm run dev
   ```

## 🖥 Commands

- `npm run dev`: Start the Vite development server and Laravel's local server.
- `php artisan native:serve`: Run the application in a native desktop window.
- `php artisan native:build`: Build the production native application.

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).
