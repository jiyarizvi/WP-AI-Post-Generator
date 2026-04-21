# 🚀 WP AI Post Generator
Generate high‑quality WordPress posts using OpenAI — with tones, bulk mode, auto‑categories, auto‑tags & featured images.
WP AI Post Generator is a powerful WordPress plugin that lets you create complete, publication‑ready blog posts using OpenAI’s Chat & Image APIs.
Perfect for news sites, crypto blogs, niche publishers, and content creators who want fast, consistent, SEO‑friendly content generation.

## ✨ Features

### 📝 AI‑Generated Blog Posts
Generate full articles using OpenAI’s Chat Completions API (gpt‑4.1‑mini by default).
Includes:

Structured headings (H2/H3)

Strong intros

Clean paragraphs

SEO‑friendly formatting

### 🎨 AI Featured Image Generator
Automatically generate a 1024×1024 featured image using OpenAI’s Images API (gpt-image-1):

Editorial‑style illustrations

Crypto/finance‑friendly aesthetic

Auto‑downloaded & attached as the post thumbnail

### 🎚️ Tone Presets
Choose the writing style that fits your publication:

News / Neutral

Formal / Analytical

Crypto‑Native / DeFi‑Savvy

Beginner‑Friendly / Educational

Each tone adjusts vocabulary, structure, and depth.

### 📦 Bulk Post Generator
Generate up to 10 posts at once:

Each post gets unique content

Titles auto‑numbered (e.g., “Bitcoin Market Update #3”)

Featured images & taxonomy applied individually

Perfect for:

Series content

Multi‑day publishing schedules

Automated content pipelines

### 🧠 Auto‑Category Detection
OpenAI analyzes your article and returns suggested categories.

The plugin will:

Create categories if they don’t exist

Assign them to the post

Only applies to post types that support categories

### 🏷️ Auto‑Tagging
OpenAI also returns relevant tags:

Automatically added to the post

Helps SEO & internal linking

Works with any post type that supports tags

### 🧩 Supports Any Post Type

Generate content for:

Posts

Pages

Custom post types (CPTs)

Portfolio items

Documentation pages

### ⚙️ Simple Settings Panel

Configure:

Add your own OpenAI API Key

Chat API Endpoint (default: https://api.openai.com/v1/chat/completions)

No extra setup required.

### 📥 Installation

Download or clone the repository:

```
git clone https://github.com/jiyarizvi/WP-AI-Post-Generator
```

Upload the folder to:

```
/wp-content/plugins/
```
Activate the plugin in WordPress → Plugins

Go to Posts → AI Post Generator

Enter your OpenAI API Key in the settings panel

Start generating content

### 🧪 Usage

1. Enter a title

2. Add a brief/instructions

3. Choose a tone

4. Select post type

5. Choose number of posts

6. Enable/disable:

  * Featured image generation

  * Auto‑categories

  * Auto‑tags

7. Click Generate Draft(s)

Your drafts will appear in the WordPress editor.

### 🔐 Requirements

* WordPress 5.8+

* PHP 7.4+

* OpenAI API key

* cURL enabled

### 🛠️ Developer Notes

* Uses ``` wp_remote_post() ``` for API calls

* Featured images are sideloaded via ``` media_handle_sideload() ```

* Auto‑taxonomy uses ``` wp_insert_term()``` and ``` wp_set_post_tags() ```

* Bulk generation loops through the generator safely

* JSON‑only responses enforced for reliability

### 📄 License

MIT License — free to use, modify, and distribute.

### ☕ Support the Developer

If you enjoy this plugin, consider supporting me at:

* Buy Me a Coffee: https://buymeacoffee.com/jiyarizvi1z

* Ko‑fi: https://ko-fi.com/cryptofanatic

### 💬 Feedback & Contributions

Pull requests are welcome!
