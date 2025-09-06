import { useEffect } from 'react';
import Header from './Header';
import { Link } from 'react-router-dom';

const PrivacyPolicy = () => {
  useEffect(() => {
    // Initialize AOS animation
    if (typeof AOS !== 'undefined') {
      AOS.init({
        duration: 1000,
      });
    }

    // Navbar scroll effect
    const handleScroll = () => {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 100) {
        navbar?.classList.add('scrolled');
      } else {
        navbar?.classList.remove('scrolled');
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <div className="wrapper privacy-page">
      <div className="main">
        <div className="inner-bg">
          <h1 className="inner-title">Privacy Policy</h1>
        </div>
        <div className="mt-50">
          <div className="container">
            <p>
              We are committed to protecting your personal information and your right to privacy. 
              This Privacy Policy explains what information we collect, how we use it, and what 
              rights you have in relation to it.
            </p>

            <h3 className="privacy-title">1. What Information We Collect</h3>
            <p>
              We collect personal data such as name, email, and usage data when you interact with our services.
            </p>

            <h3 className="privacy-title">2. How We Use Your Information</h3>
            <ul className="privacy-ul">
              <li>To provide and improve our services</li>
              <li>To respond to inquiries and support needs</li>
              <li>To send updates or marketing (with your permission)</li>
            </ul>

            <h3 className="privacy-title">3. Data Sharing</h3>
            <p>
              We do not sell or rent your data. We only share data with trusted third parties who 
              assist in operating our website, provided they agree to keep your information confidential.
            </p>

            <h3 className="privacy-title">4. Use of Cookies</h3>
            <p>
              We use cookies to enhance your browsing experience. You can disable cookies in your browser settings.
            </p>

            <h3 className="privacy-title">5. Security</h3>
            <p>
              We use secure systems and processes to protect your personal data from unauthorized 
              access or disclosure.
            </p>

            <h3 className="privacy-title">6. Your Rights</h3>
            <p>
              You have the right to access, update, or delete your personal data. You may contact 
              us at any time to exercise these rights.
            </p>

            <h3 className="privacy-title">7. Policy Updates</h3>
            <p>
              We may update this Privacy Policy from time to time. Any changes will be posted on 
              this page with an updated date.
            </p>

            <h3 className="privacy-title">8. Contact</h3>
            <p>
              If you have questions about this policy, please contact us at{' '}
              <a href="mailto:privacy@yourcompany.com">privacy@yourcompany.com</a>.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PrivacyPolicy;