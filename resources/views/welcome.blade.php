<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verifier - Validate Emails Instantly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ==================== CSS RESET & BASE ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ul {
            list-style: none;
        }

        /* ==================== COLOR VARIABLES ==================== */
        :root {
            /* Dark Theme Colors */
            --dark-bg: #0a0f1c;
            --dark-bg-secondary: #0d1321;
            --dark-text: #ffffff;
            --dark-text-muted: #a0aec0;
            --dark-card-bg: rgba(255, 255, 255, 0.05);
            --dark-border: rgba(255, 255, 255, 0.1);

            /* Light Theme Colors */
            --light-bg: #ffffff;
            --light-bg-secondary: #f8fafc;
            --light-text: #1a202c;
            --light-text-muted: #64748b;
            --light-card-bg: #ffffff;
            --light-border: #e2e8f0;

            /* Accent Colors */
            --primary-start: #667eea;
            --primary-end: #764ba2;
            --success: #48bb78;
            --warning: #fbbf24;
        }

        /* ==================== UTILITY CLASSES ==================== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section {
            padding: 100px 0;
        }

        /* Dark Section Styling */
        .section-dark {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

        .section-dark .section-title p,
        .section-dark .text-muted {
            color: var(--dark-text-muted);
        }

        /* Light Section Styling */
        .section-light {
            background-color: var(--light-bg);
            color: var(--light-text);
        }

        .section-light-alt {
            background-color: var(--light-bg-secondary);
            color: var(--light-text);
        }

        .section-light .section-title p,
        .section-light .text-muted,
        .section-light-alt .section-title p,
        .section-light-alt .text-muted {
            color: var(--light-text-muted);
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .btn {
            display: inline-block;
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: #fff;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-start);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-start);
            border: 2px solid var(--primary-start);
        }

        .btn-outline:hover {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: #fff;
            border-color: transparent;
        }

        .btn-light {
            background: transparent;
            color: var(--light-text);
            border: 2px solid var(--light-border);
        }

        .btn-light:hover {
            background: var(--light-bg-secondary);
            border-color: var(--primary-start);
            color: var(--primary-start);
        }

        /* ==================== 1. NAVIGATION ==================== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 20px 0;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(10, 15, 28, 0.98);
            backdrop-filter: blur(20px);
            padding: 15px 0;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo i {
            color: var(--primary-start);
            font-size: 1.8rem;
        }

        .logo span {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a {
            color: var(--dark-text-muted);
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-btn {
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .btn-login {
            background: transparent;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .btn-login:hover {
            color: var(--primary-start);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ==================== 2. HERO SECTION (DARK) ==================== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
            background: var(--dark-bg);
            color: var(--dark-text);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(118, 75, 162, 0.1) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-20px, -20px) rotate(5deg); }
        }

        .hero .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 25px;
        }

        .hero-content h1 span {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: var(--dark-text-muted);
            margin-bottom: 35px;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }

        .trust-badges {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark-text-muted);
            font-size: 0.9rem;
        }

        .trust-badge i {
            color: var(--success);
            font-size: 1.1rem;
        }

        .hero-visual {
            position: relative;
        }

        .hero-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(20px);
        }

        .email-input-demo {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .email-input-demo label {
            display: block;
            color: var(--dark-text-muted);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .email-input-demo input {
            width: 100%;
            padding: 15px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .email-input-demo input:focus {
            border-color: var(--primary-start);
        }

        .verify-btn-demo {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .verify-btn-demo:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .result-preview {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .result-item span:first-child {
            color: var(--dark-text-muted);
        }

        .result-item .valid {
            color: var(--success);
            font-weight: 600;
        }

        .result-item .score {
            color: var(--primary-start);
            font-weight: 600;
        }

        /* ==================== 3. STATISTICS (LIGHT) ==================== */
        .statistics {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 80px 0;
            border-top: 1px solid var(--light-border);
            border-bottom: 1px solid var(--light-border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }

        .stat-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--light-text-muted);
            font-size: 1rem;
            font-weight: 500;
        }

        /* ==================== 4. PRICING (LIGHT) ==================== */
        .pricing {
            background: var(--light-bg);
            color: var(--light-text);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            align-items: start;
        }

        .pricing-card {
            background: var(--light-bg);
            border: 2px solid var(--light-border);
            border-radius: 25px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.15);
            border-color: var(--primary-start);
        }

        .pricing-card.popular {
            background: linear-gradient(145deg, var(--dark-bg) 0%, var(--dark-bg-secondary) 100%);
            border-color: var(--primary-start);
            color: var(--dark-text);
            transform: scale(1.05);
        }

        .pricing-card.popular:hover {
            transform: scale(1.05) translateY(-10px);
        }

        .popular-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: white;
            padding: 8px 25px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pricing-card h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
        }

        .pricing-card .subtitle {
            color: var(--light-text-muted);
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        .pricing-card.popular .subtitle {
            color: var(--dark-text-muted);
        }

        .price {
            margin-bottom: 30px;
        }

        .price .amount {
            font-size: 3rem;
            font-weight: 800;
        }

        .price .period {
            color: var(--light-text-muted);
            font-size: 0.95rem;
        }

        .pricing-card.popular .price .period {
            color: var(--dark-text-muted);
        }

        .pricing-features {
            margin-bottom: 30px;
            text-align: left;
        }

        .pricing-features li {
            padding: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--light-text-muted);
            font-size: 0.95rem;
        }

        .pricing-card.popular .pricing-features li {
            color: var(--dark-text-muted);
        }

        .pricing-features li i {
            color: var(--success);
            font-size: 0.9rem;
        }

        .pricing-card .btn {
            width: 100%;
        }

        .money-back {
            text-align: center;
            margin-top: 50px;
            padding: 30px;
            background: linear-gradient(145deg, rgba(72, 187, 120, 0.1) 0%, rgba(72, 187, 120, 0.05) 100%);
            border: 2px solid rgba(72, 187, 120, 0.3);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .money-back i {
            font-size: 2.5rem;
            color: var(--success);
        }

        .money-back div {
            text-align: left;
        }

        .money-back h4 {
            color: var(--success);
            margin-bottom: 5px;
        }

        .money-back p {
            color: var(--light-text-muted);
            font-size: 0.9rem;
        }

        /* ==================== 5. HOW IT WORKS (DARK) ==================== */
        .how-it-works {
            background: var(--dark-bg);
            color: var(--dark-text);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            position: relative;
        }

        .steps-grid::before {
            content: '';
            position: absolute;
            top: 60px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-start), var(--primary-end));
            opacity: 0.3;
        }

        .step-card {
            text-align: center;
            padding: 40px 25px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .step-card:hover {
            transform: translateY(-10px);
            border-color: rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
        }

        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 25px;
        }

        .step-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .step-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .step-card p {
            color: var(--dark-text-muted);
            font-size: 0.95rem;
        }

        /* ==================== 6. WHY CHOOSE US (LIGHT) ==================== */
        .why-choose {
            background: var(--light-bg-secondary);
            color: var(--light-text);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            padding: 40px 30px;
            background: var(--light-bg);
            border: 2px solid var(--light-border);
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-start);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.1);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 25px;
        }

        .feature-icon i {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--light-text);
        }

        .feature-card p {
            color: var(--light-text-muted);
            font-size: 0.95rem;
        }

        /* ==================== 7. TESTIMONIALS (LIGHT) ==================== */
        .testimonials {
            background: var(--light-bg);
            color: var(--light-text);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .testimonial-card {
            background: var(--light-bg);
            border: 2px solid var(--light-border);
            border-radius: 20px;
            padding: 35px;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-start);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.1);
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .testimonial-info h4 {
            font-size: 1.1rem;
            margin-bottom: 3px;
            color: var(--light-text);
        }

        .testimonial-info p {
            color: var(--light-text-muted);
            font-size: 0.85rem;
        }

        .testimonial-rating {
            color: var(--warning);
            margin-bottom: 15px;
        }

        .testimonial-text {
            color: var(--light-text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
            font-style: italic;
        }

        /* ==================== 8. FAQ (DARK) ==================== */
        .faq {
            background: var(--dark-bg-secondary);
            color: var(--dark-text);
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: rgba(102, 126, 234, 0.2);
        }

        .faq-question {
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--dark-text);
        }

        .faq-question i {
            transition: transform 0.3s ease;
            color: var(--primary-start);
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 500px;
        }

        .faq-answer p {
            padding: 0 30px 25px;
            color: var(--dark-text-muted);
            line-height: 1.7;
        }

        /* ==================== 9. CONTACT (LIGHT) ==================== */
        .contact {
            background: var(--light-bg-secondary);
            color: var(--light-text);
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .contact-info h3 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--light-text);
        }

        .contact-info > p {
            color: var(--light-text-muted);
            margin-bottom: 30px;
            font-size: 1.05rem;
        }

        .contact-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .contact-item i {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-start);
            font-size: 1.2rem;
        }

        .contact-item span {
            color: var(--light-text-muted);
        }

        .contact-form {
            background: var(--light-bg);
            border: 2px solid var(--light-border);
            border-radius: 25px;
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--light-text-muted);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid var(--light-border);
            background: var(--light-bg);
            color: var(--light-text);
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-start);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .contact-form .btn {
            width: 100%;
        }

        /* ==================== 10. FOOTER (DARK) ==================== */
        .footer {
            background: var(--dark-bg);
            color: var(--dark-text);
            padding: 80px 0 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 60px;
        }

        .footer-brand .logo {
            margin-bottom: 20px;
        }

        .footer-brand p {
            color: var(--dark-text-muted);
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-text-muted);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: #fff;
            transform: translateY(-3px);
        }

        .footer-column h4 {
            font-size: 1.1rem;
            margin-bottom: 25px;
            color: var(--dark-text);
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: var(--dark-text-muted);
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .footer-column ul li a:hover {
            color: var(--primary-start);
        }

        .footer-bottom {
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom p {
            color: var(--dark-text-muted);
            font-size: 0.9rem;
        }

        .footer-bottom-links {
            display: flex;
            gap: 25px;
        }

        .footer-bottom-links a {
            color: var(--dark-text-muted);
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-bottom-links a:hover {
            color: var(--primary-start);
        }

        /* ==================== 11. FLOATING CHAT BUTTON ==================== */
        .chat-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 999;
            border: none;
        }

        .chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }

        .chat-button i {
            font-size: 1.5rem;
            color: #fff;
        }

        .chat-tooltip {
            position: absolute;
            right: 80px;
            background: #fff;
            color: var(--dark-bg);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .chat-button:hover .chat-tooltip {
            opacity: 1;
            visibility: visible;
        }

        /* ==================== MOBILE MENU ==================== */
        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 15, 28, 0.98);
            z-index: 1001;
            padding: 100px 30px 30px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .mobile-menu.active {
            transform: translateX(0);
        }

        .mobile-menu-close {
            position: absolute;
            top: 25px;
            right: 25px;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.8rem;
            cursor: pointer;
        }

        .mobile-menu ul {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .mobile-menu ul li a {
            font-size: 1.3rem;
            color: #fff;
            font-weight: 500;
        }

        .mobile-auth-btns {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* ==================== AUTH MODAL ==================== */
        .auth-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 15, 28, 0.9);
            backdrop-filter: blur(10px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .auth-modal.active {
            display: flex;
        }

        .auth-modal-content {
            background: var(--light-bg);
            border-radius: 25px;
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            position: relative;
            color: var(--light-text);
        }

        .auth-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--light-text-muted);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .auth-modal-close:hover {
            color: var(--light-text);
        }

        .auth-modal-content h2 {
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-modal-content .subtitle {
            text-align: center;
            color: var(--light-text-muted);
            margin-bottom: 30px;
        }

        .auth-form .form-group {
            margin-bottom: 20px;
        }

        .auth-form .btn {
            width: 100%;
            margin-top: 10px;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: var(--light-text-muted);
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--light-border);
        }

        .auth-divider span {
            padding: 0 15px;
            font-size: 0.9rem;
        }

        .social-auth-btns {
            display: flex;
            gap: 15px;
        }

        .social-auth-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--light-border);
            border-radius: 12px;
            background: var(--light-bg);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
            color: var(--light-text);
        }

        .social-auth-btn:hover {
            border-color: var(--primary-start);
            background: var(--light-bg-secondary);
        }

        .social-auth-btn i {
            font-size: 1.2rem;
        }

        .auth-switch {
            text-align: center;
            margin-top: 25px;
            color: var(--light-text-muted);
        }

        .auth-switch a {
            color: var(--primary-start);
            font-weight: 600;
            cursor: pointer;
        }

        .auth-switch a:hover {
            text-decoration: underline;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1200px) {
            .pricing-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .pricing-card.popular {
                transform: scale(1);
            }
        }

        @media (max-width: 992px) {
            .nav-links,
            .nav-buttons {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .mobile-menu {
                display: block;
            }

            .hero .container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                margin: 0 auto 35px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .trust-badges {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps-grid::before {
                display: none;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .testimonials-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .contact-container {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .section {
                padding: 70px 0;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }

            .stat-item {
                padding: 20px;
            }

            .stat-number {
                font-size: 2.2rem;
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .social-links {
                justify-content: center;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            .money-back {
                flex-direction: column;
                text-align: center;
            }

            .money-back div {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .hero-content h1 {
                font-size: 1.8rem;
            }

            .pricing-card {
                padding: 30px 20px;
            }

            .contact-form {
                padding: 25px;
            }

            .chat-button {
                width: 55px;
                height: 55px;
                bottom: 20px;
                right: 20px;
            }

            .auth-modal-content {
                padding: 40px 25px;
            }
        }
    </style>
</head>
<body>

    <!-- ==================== 1. NAVIGATION ==================== -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="#" class="logo">
                <i class="fas fa-envelope-circle-check"></i>
                <span>EmailVerifier</span>
            </a>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <button class="btn-login" id="loginBtn">Login</button>
                <button class="btn-register" id="registerBtn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </div>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="mobile-menu-close" id="mobileMenuClose">
            <i class="fas fa-times"></i>
        </button>
        <ul>
            <li><a href="#home" class="mobile-link">Home</a></li>
            <li><a href="#pricing" class="mobile-link">Pricing</a></li>
            <li><a href="#how-it-works" class="mobile-link">How It Works</a></li>
            <li><a href="#features" class="mobile-link">Features</a></li>
            <li><a href="#testimonials" class="mobile-link">Testimonials</a></li>
            <li><a href="#faq" class="mobile-link">FAQ</a></li>
            <li><a href="#contact" class="mobile-link">Contact</a></li>
        </ul>
        <div class="mobile-auth-btns">
            <button class="btn btn-secondary" id="mobileLoginBtn">Login</button>
            <button class="btn btn-primary" id="mobileRegisterBtn">Register</button>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="auth-modal" id="loginModal">
        <div class="auth-modal-content">
            <button class="auth-modal-close" id="loginModalClose">
                <i class="fas fa-times"></i>
            </button>
            <h2>Welcome Back!</h2>
            <p class="subtitle">Login to your dashboard</p>
            <form class="auth-form" id="loginForm">
                <div class="form-group">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <div class="auth-divider">
                <span>or continue with</span>
            </div>
            <div class="social-auth-btns">
                <button class="social-auth-btn">
                    <i class="fab fa-google"></i> Google
                </button>
                <button class="social-auth-btn">
                    <i class="fab fa-github"></i> GitHub
                </button>
            </div>
            <p class="auth-switch">
                Don't have an account? <a id="switchToRegister">Register</a>
            </p>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="auth-modal" id="registerModal">
        <div class="auth-modal-content">
            <button class="auth-modal-close" id="registerModalClose">
                <i class="fas fa-times"></i>
            </button>
            <h2>Create Account</h2>
            <p class="subtitle">Start verifying emails for free</p>
            <form class="auth-form" id="registerForm">
                <div class="form-group">
                    <label for="registerName">Full Name</label>
                    <input type="text" id="registerName" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label for="registerEmail">Email Address</label>
                    <input type="email" id="registerEmail" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <input type="password" id="registerPassword" placeholder="Create a password" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            <div class="auth-divider">
                <span>or continue with</span>
            </div>
            <div class="social-auth-btns">
                <button class="social-auth-btn">
                    <i class="fab fa-google"></i> Google
                </button>
                <button class="social-auth-btn">
                    <i class="fab fa-github"></i> GitHub
                </button>
            </div>
            <p class="auth-switch">
                Already have an account? <a id="switchToLogin">Login</a>
            </p>
        </div>
    </div>

    <!-- ==================== 2. HERO SECTION (DARK) ==================== -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Verify Emails <span>Instantly</span> with 99% Accuracy</h1>
                <p>Clean your email lists, reduce bounce rates, and improve deliverability. Our advanced verification system validates emails in real-time.</p>
                <div class="hero-buttons">
                    <a href="#pricing" class="btn btn-primary">
                        <i class="fas fa-rocket"></i> Try Free Now
                    </a>
                    <a href="#how-it-works" class="btn btn-secondary">
                        <i class="fas fa-play-circle"></i> See How It Works
                    </a>
                </div>
                <div class="trust-badges">
                    <div class="trust-badge">
                        <i class="fas fa-shield-halved"></i>
                        <span>SSL Secured</span>
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-check-circle"></i>
                        <span>GDPR Compliant</span>
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-lock"></i>
                        <span>256-bit Encryption</span>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-card">
                    <div class="email-input-demo">
                        <label>Enter Email to Verify</label>
                        <input type="email" placeholder="example@domain.com" id="demoEmail">
                    </div>
                    <button class="verify-btn-demo" id="verifyBtn">
                        <i class="fas fa-search"></i> Verify Email
                    </button>
                    <div class="result-preview" id="resultPreview" style="margin-top: 25px; display: none;">
                        <div class="result-item">
                            <span>Status</span>
                            <span class="valid"><i class="fas fa-check-circle"></i> Valid</span>
                        </div>
                        <div class="result-item">
                            <span>Deliverable</span>
                            <span class="valid"><i class="fas fa-check-circle"></i> Yes</span>
                        </div>
                        <div class="result-item">
                            <span>Quality Score</span>
                            <span class="score">95/100</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 3. STATISTICS (LIGHT) ==================== -->
    <section class="statistics">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">10M+</div>
                    <div class="stat-label">Emails Verified</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">5,000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">Uptime Guarantee</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">150+</div>
                    <div class="stat-label">Countries Served</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 4. PRICING (LIGHT) - MOVED HERE ==================== -->
    <section class="pricing section section-light" id="pricing">
        <div class="container">
            <div class="section-title">
                <h2>Simple, Transparent Pricing</h2>
                <p>Choose the perfect plan for your email verification needs</p>
            </div>
            <div class="pricing-grid">
                <!-- Free Plan -->
                <div class="pricing-card">
                    <h3>Free</h3>
                    <p class="subtitle">For getting started</p>
                    <div class="price">
                        <span class="amount">$0</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> 100 verifications/month</li>
                        <li><i class="fas fa-check"></i> Single email verification</li>
                        <li><i class="fas fa-check"></i> Basic email checks</li>
                        <li><i class="fas fa-check"></i> Email support</li>
                        <li><i class="fas fa-check"></i> Dashboard access</li>
                    </ul>
                    <a href="#" class="btn btn-outline" id="freeStartBtn">Get Started</a>
                </div>

                <!-- Basic Plan -->
                <div class="pricing-card">
                    <h3>Basic</h3>
                    <p class="subtitle">For small businesses</p>
                    <div class="price">
                        <span class="amount">$29</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> 5,000 verifications/month</li>
                        <li><i class="fas fa-check"></i> Bulk email upload</li>
                        <li><i class="fas fa-check"></i> Advanced email checks</li>
                        <li><i class="fas fa-check"></i> Priority email support</li>
                        <li><i class="fas fa-check"></i> Export to CSV</li>
                    </ul>
                    <a href="#" class="btn btn-outline">Choose Basic</a>
                </div>

                <!-- Pro Plan (Popular) -->
                <div class="pricing-card popular">
                    <div class="popular-badge">Most Popular</div>
                    <h3>Pro</h3>
                    <p class="subtitle">For growing teams</p>
                    <div class="price">
                        <span class="amount">$79</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> 25,000 verifications/month</li>
                        <li><i class="fas fa-check"></i> API access</li>
                        <li><i class="fas fa-check"></i> Real-time verification</li>
                        <li><i class="fas fa-check"></i> Priority support</li>
                        <li><i class="fas fa-check"></i> Team collaboration</li>
                        <li><i class="fas fa-check"></i> Detailed analytics</li>
                    </ul>
                    <a href="#" class="btn btn-primary">Choose Pro</a>
                </div>

                <!-- Enterprise Plan -->
                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <p class="subtitle">For large organizations</p>
                    <div class="price">
                        <span class="amount">$199</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Unlimited verifications</li>
                        <li><i class="fas fa-check"></i> Dedicated API</li>
                        <li><i class="fas fa-check"></i> Custom integrations</li>
                        <li><i class="fas fa-check"></i> 24/7 phone support</li>
                        <li><i class="fas fa-check"></i> SLA guarantee</li>
                        <li><i class="fas fa-check"></i> Account manager</li>
                    </ul>
                    <a href="#" class="btn btn-outline">Contact Sales</a>
                </div>
            </div>

            <!-- Money Back Guarantee -->
            <div class="money-back">
                <i class="fas fa-medal"></i>
                <div>
                    <h4>30-Day Money-Back Guarantee</h4>
                    <p>Not satisfied? Get a full refund within 30 days, no questions asked.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 5. HOW IT WORKS (DARK) ==================== -->
    <section class="how-it-works section section-dark" id="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Verify your email lists in just 4 simple steps</p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h3>Upload Your List</h3>
                    <p>Upload your email list in CSV, TXT, or Excel format. Drag & drop supported.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon"><i class="fas fa-cogs"></i></div>
                    <h3>Automatic Processing</h3>
                    <p>Our system runs 20+ verification checks on each email address.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon"><i class="fas fa-chart-bar"></i></div>
                    <h3>Review Results</h3>
                    <p>Get detailed reports with validity scores and actionable insights.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-icon"><i class="fas fa-download"></i></div>
                    <h3>Download Clean List</h3>
                    <p>Export your verified, clean email list ready for your campaigns.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 6. WHY CHOOSE US (LIGHT) ==================== -->
    <section class="why-choose section section-light-alt" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Us</h2>
                <p>Industry-leading features that make us the best choice for email verification</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>99% Accuracy Rate</h3>
                    <p>Our advanced algorithms ensure the highest accuracy in email verification, reducing false positives.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Lightning Fast</h3>
                    <p>Process thousands of emails per minute with our optimized verification engine.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <h3>Bulk Upload</h3>
                    <p>Upload and verify millions of emails at once. Support for CSV, TXT, and Excel files.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure & Private</h3>
                    <p>Your data is encrypted with 256-bit SSL. We never store or share your email lists.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                    <h3>API Integration</h3>
                    <p>Easy-to-use REST API for seamless integration with your existing tools and workflows.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Our dedicated support team is always ready to help you with any questions or issues.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 7. TESTIMONIALS (LIGHT) ==================== -->
    <section class="testimonials section section-light" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>What Our Customers Say</h2>
                <p>Trusted by thousands of businesses worldwide</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">JD</div>
                        <div class="testimonial-info">
                            <h4>John Davidson</h4>
                            <p>Marketing Director, TechCorp</p>
                        </div>
                    </div>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"EmailVerifier reduced our bounce rate by 85%. The accuracy is incredible and the speed is unmatched. Highly recommend!"</p>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">SM</div>
                        <div class="testimonial-info">
                            <h4>Sarah Mitchell</h4>
                            <p>CEO, StartupXYZ</p>
                        </div>
                    </div>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Best email verification service we've used. The API integration was seamless and customer support is top-notch."</p>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">MR</div>
                        <div class="testimonial-info">
                            <h4>Michael Roberts</h4>
                            <p>Sales Manager, GlobalSales Inc</p>
                        </div>
                    </div>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="testimonial-text">"We process over 100k emails monthly. EmailVerifier handles it effortlessly and saves us thousands in bounced emails."</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 8. FAQ (DARK) ==================== -->
    <section class="faq section section-dark" id="faq">
        <div class="container">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
                <p>Got questions? We've got answers</p>
            </div>
            <div class="faq-container">
                <div class="faq-item active">
                    <div class="faq-question">
                        <span>How accurate is your email verification?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Our email verification system maintains a 99% accuracy rate. We use a combination of syntax checking, domain validation, MX record verification, and SMTP verification to ensure the highest accuracy possible.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>How long does verification take?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Single email verification takes less than 1 second. For bulk uploads, we process approximately 10,000 emails per minute, depending on your plan and server response times.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Is my data secure?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolutely! We use 256-bit SSL encryption for all data transfers. Your email lists are processed in memory and never stored on our servers. We are fully GDPR compliant.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>What file formats do you support?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>We support CSV, TXT, XLS, and XLSX file formats. You can also copy-paste email addresses directly or use our API for programmatic access.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Do you offer refunds?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes! We offer a 30-day money-back guarantee. If you're not satisfied with our service for any reason, contact our support team for a full refund.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I integrate with my existing tools?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes! Our REST API allows easy integration with any platform. We also offer native integrations with popular tools like Mailchimp, HubSpot, Salesforce, and Zapier.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 9. CONTACT (LIGHT) ==================== -->
    <section class="contact section section-light-alt" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Get In Touch</h2>
                <p>Have questions? We'd love to hear from you</p>
            </div>
            <div class="contact-container">
                <div class="contact-info">
                    <h3>Let's Talk</h3>
                    <p>Whether you have a question about features, pricing, or anything else, our team is ready to answer all your questions.</p>
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>support@emailverifier.com</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>+1 (555) 123-4567</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>123 Tech Street, San Francisco, CA</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <span>Mon - Fri: 9AM - 6PM (PST)</span>
                        </div>
                    </div>
                </div>
                <div class="contact-form">
                    <form id="contactForm">
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" placeholder="John Doe" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="john@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Your Message</label>
                            <textarea id="message" name="message" placeholder="How can we help you?" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 10. FOOTER (DARK) ==================== -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#" class="logo">
                        <i class="fas fa-envelope-circle-check"></i>
                        <span>EmailVerifier</span>
                    </a>
                    <p>The most accurate and reliable email verification service. Clean your lists, boost deliverability, and grow your business.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h4>Product</h4>
                    <ul>
                        <li><a href="#">Features</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">API Documentation</a></li>
                        <li><a href="#">Integrations</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Press</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Status</a></li>
                        <li><a href="#">Report a Bug</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 EmailVerifier. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ==================== 11. FLOATING CHAT BUTTON ==================== -->
    <button class="chat-button" id="chatButton">
        <i class="fas fa-comments"></i>
        <span class="chat-tooltip">Need help? Chat with us!</span>
    </button>

    <!-- ==================== JAVASCRIPT ==================== -->
    <script>
        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuClose = document.getElementById('mobileMenuClose');
        const mobileLinks = document.querySelectorAll('.mobile-link');

        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.add('active');
        });

        mobileMenuClose.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
        });

        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
            });
        });

        // Auth Modals
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const mobileLoginBtn = document.getElementById('mobileLoginBtn');
        const mobileRegisterBtn = document.getElementById('mobileRegisterBtn');
        const loginModal = document.getElementById('loginModal');
        const registerModal = document.getElementById('registerModal');
        const loginModalClose = document.getElementById('loginModalClose');
        const registerModalClose = document.getElementById('registerModalClose');
        const switchToRegister = document.getElementById('switchToRegister');
        const switchToLogin = document.getElementById('switchToLogin');
        const freeStartBtn = document.getElementById('freeStartBtn');

        // Open Login Modal
        loginBtn.addEventListener('click', () => {
            loginModal.classList.add('active');
        });

        mobileLoginBtn.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            loginModal.classList.add('active');
        });

        // Open Register Modal
        registerBtn.addEventListener('click', () => {
            registerModal.classList.add('active');
        });

        mobileRegisterBtn.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            registerModal.classList.add('active');
        });

        freeStartBtn.addEventListener('click', (e) => {
            e.preventDefault();
            registerModal.classList.add('active');
        });

        // Close Modals
        loginModalClose.addEventListener('click', () => {
            loginModal.classList.remove('active');
        });

        registerModalClose.addEventListener('click', () => {
            registerModal.classList.remove('active');
        });

        // Switch between modals
        switchToRegister.addEventListener('click', () => {
            loginModal.classList.remove('active');
            registerModal.classList.add('active');
        });

        switchToLogin.addEventListener('click', () => {
            registerModal.classList.remove('active');
            loginModal.classList.add('active');
        });

        // Close modal on outside click
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                loginModal.classList.remove('active');
            }
        });

        registerModal.addEventListener('click', (e) => {
            if (e.target === registerModal) {
                registerModal.classList.remove('active');
            }
        });

        // Login Form Submit
        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = loginForm.querySelector('.btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Success!';
                setTimeout(() => {
                    loginModal.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
                    alert('Welcome back! Redirecting to dashboard...');
                }, 1000);
            }, 1500);
        });

        // Register Form Submit
        const registerForm = document.getElementById('registerForm');
        registerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = registerForm.querySelector('.btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Account Created!';
                setTimeout(() => {
                    registerModal.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
                    alert('Account created successfully! Redirecting to dashboard...');
                }, 1000);
            }, 1500);
        });

        // Demo email verification
        const verifyBtn = document.getElementById('verifyBtn');
        const demoEmail = document.getElementById('demoEmail');
        const resultPreview = document.getElementById('resultPreview');

        verifyBtn.addEventListener('click', () => {
            const email = demoEmail.value.trim();
            if (email && email.includes('@') && email.includes('.')) {
                verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                setTimeout(() => {
                    verifyBtn.innerHTML = '<i class="fas fa-check"></i> Verified!';
                    resultPreview.style.display = 'flex';
                    setTimeout(() => {
                        verifyBtn.innerHTML = '<i class="fas fa-search"></i> Verify Email';
                    }, 2000);
                }, 1500);
            } else {
                demoEmail.style.borderColor = '#f56565';
                setTimeout(() => {
                    demoEmail.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                }, 2000);
            }
        });

        // FAQ accordion
        const faqItems = document.querySelectorAll('.faq-item');

        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');

                faqItems.forEach(faq => faq.classList.remove('active'));

                if (!isActive) {
                    item.classList.add('active');
                }
            });
        });

        // Contact form submission
        const contactForm = document.getElementById('contactForm');
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = contactForm.querySelector('.btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Message Sent!';
                contactForm.reset();
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            }, 1500);
        });

        // Chat button functionality
        const chatButton = document.getElementById('chatButton');
        chatButton.addEventListener('click', () => {
            alert('Chat feature coming soon! Please use the contact form or email us at support@emailverifier.com');
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.step-card, .feature-card, .pricing-card, .testimonial-card, .stat-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>

</body>
</html>
