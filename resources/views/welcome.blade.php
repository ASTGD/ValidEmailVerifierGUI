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
            background-color: #0a0f1c;
            color: #ffffff;
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

        /* ==================== UTILITY CLASSES ==================== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section {
            padding: 100px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            color: #a0aec0;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-color: #667eea;
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
            background: rgba(10, 15, 28, 0.95);
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
            color: #667eea;
            font-size: 1.8rem;
        }

        .logo span {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 35px;
        }

        .nav-links a {
            color: #a0aec0;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-btn {
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ==================== 2. HERO SECTION ==================== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: #a0aec0;
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
            color: #a0aec0;
            font-size: 0.9rem;
        }

        .trust-badge i {
            color: #48bb78;
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
            color: #a0aec0;
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
            border-color: #667eea;
        }

        .verify-btn-demo {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #a0aec0;
        }

        .result-item .valid {
            color: #48bb78;
            font-weight: 600;
        }

        .result-item .score {
            color: #667eea;
            font-weight: 600;
        }

        /* ==================== 3. STATISTICS ==================== */
        .statistics {
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 60px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #a0aec0;
            font-size: 1rem;
        }

        /* ==================== 4. HOW IT WORKS ==================== */
        .how-it-works {
            background: #0d1321;
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
            background: linear-gradient(90deg, #667eea, #764ba2);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .step-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .step-card p {
            color: #a0aec0;
            font-size: 0.95rem;
        }

        /* ==================== 5. WHY CHOOSE US ==================== */
        .why-choose {
            background: #0a0f1c;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            padding: 40px 30px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(102, 126, 234, 0.3);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 25px;
        }

        .feature-icon i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #a0aec0;
            font-size: 0.95rem;
        }

        /* ==================== 6. PRICING ==================== */
        .pricing {
            background: #0d1321;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            align-items: start;
        }

        .pricing-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .pricing-card.popular {
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            border-color: rgba(102, 126, 234, 0.3);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #a0aec0;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        .price {
            margin-bottom: 30px;
        }

        .price .amount {
            font-size: 3rem;
            font-weight: 800;
        }

        .price .period {
            color: #a0aec0;
            font-size: 0.95rem;
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
            color: #a0aec0;
            font-size: 0.95rem;
        }

        .pricing-features li i {
            color: #48bb78;
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
            border: 1px solid rgba(72, 187, 120, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .money-back i {
            font-size: 2.5rem;
            color: #48bb78;
        }

        .money-back div {
            text-align: left;
        }

        .money-back h4 {
            color: #48bb78;
            margin-bottom: 5px;
        }

        .money-back p {
            color: #a0aec0;
            font-size: 0.9rem;
        }

        /* ==================== 7. TESTIMONIALS ==================== */
        .testimonials {
            background: #0a0f1c;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .testimonial-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 35px;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            border-color: rgba(102, 126, 234, 0.3);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .testimonial-info h4 {
            font-size: 1.1rem;
            margin-bottom: 3px;
        }

        .testimonial-info p {
            color: #a0aec0;
            font-size: 0.85rem;
        }

        .testimonial-rating {
            color: #fbbf24;
            margin-bottom: 15px;
        }

        .testimonial-text {
            color: #a0aec0;
            font-size: 0.95rem;
            line-height: 1.7;
            font-style: italic;
        }

        /* ==================== 8. FAQ ==================== */
        .faq {
            background: #0d1321;
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
        }

        .faq-question i {
            transition: transform 0.3s ease;
            color: #667eea;
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
            color: #a0aec0;
            line-height: 1.7;
        }

        /* ==================== 9. CONTACT ==================== */
        .contact {
            background: #0a0f1c;
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
        }

        .contact-info p {
            color: #a0aec0;
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
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 1.2rem;
        }

        .contact-item span {
            color: #a0aec0;
        }

        .contact-form {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #a0aec0;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .contact-form .btn {
            width: 100%;
        }

        /* ==================== 10. FOOTER ==================== */
        .footer {
            background: #070a12;
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
            color: #a0aec0;
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
            color: #a0aec0;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            transform: translateY(-3px);
        }

        .footer-column h4 {
            font-size: 1.1rem;
            margin-bottom: 25px;
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: #a0aec0;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .footer-column ul li a:hover {
            color: #667eea;
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
            color: #a0aec0;
            font-size: 0.9rem;
        }

        .footer-bottom-links {
            display: flex;
            gap: 25px;
        }

        .footer-bottom-links a {
            color: #a0aec0;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-bottom-links a:hover {
            color: #667eea;
        }

        /* ==================== 11. FLOATING CHAT BUTTON ==================== */
        .chat-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #0a0f1c;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
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
            .nav-links {
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
                gap: 30px;
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
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#" class="btn btn-primary nav-btn">Try Free</a></li>
            </ul>
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
            <li><a href="#how-it-works" class="mobile-link">How It Works</a></li>
            <li><a href="#features" class="mobile-link">Features</a></li>
            <li><a href="#pricing" class="mobile-link">Pricing</a></li>
            <li><a href="#testimonials" class="mobile-link">Testimonials</a></li>
            <li><a href="#faq" class="mobile-link">FAQ</a></li>
            <li><a href="#contact" class="mobile-link">Contact</a></li>
            <li><a href="#" class="btn btn-primary" style="margin-top: 20px;">Try Free</a></li>
        </ul>
    </div>

    <!-- ==================== 2. HERO SECTION ==================== -->
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

    <!-- ==================== 3. STATISTICS ==================== -->
    <section class="statistics">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" data-target="10">10M+</div>
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

    <!-- ==================== 4. HOW IT WORKS ==================== -->
    <section class="how-it-works section" id="how-it-works">
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

    <!-- ==================== 5. WHY CHOOSE US ==================== -->
    <section class="why-choose section" id="features">
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

    <!-- ==================== 6. PRICING ==================== -->
    <section class="pricing section" id="pricing">
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
                    <a href="#" class="btn btn-secondary">Get Started</a>
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
                    <a href="#" class="btn btn-secondary">Choose Basic</a>
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
                    <a href="#" class="btn btn-secondary">Contact Sales</a>
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

    <!-- ==================== 7. TESTIMONIALS ==================== -->
    <section class="testimonials section" id="testimonials">
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

    <!-- ==================== 8. FAQ ==================== -->
    <section class="faq section" id="faq">
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

    <!-- ==================== 9. CONTACT ==================== -->
    <section class="contact section" id="contact">
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

    <!-- ==================== 10. FOOTER ==================== -->
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

                // Close all items
                faqItems.forEach(faq => faq.classList.remove('active'));

                // Open clicked item if it wasn't active
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

        document.querySelectorAll('.step-card, .feature-card, .pricing-card, .testimonial-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>

</body>
</html>
