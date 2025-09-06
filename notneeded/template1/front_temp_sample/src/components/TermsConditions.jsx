const TermsConditions = () => {
  return (
    <div className="wrapper terms-page">
      <div className="main">
        <div className="inner-bg">
          <h1 className="inner-title">Terms & Conditions</h1>
        </div>
        <div className="mt-50">
          <div className="container">
            <p>
              Welcome to LoanFlow. These terms and conditions outline the rules and regulations 
              for the use of our website and services.
            </p>

            <h3 className="terms-title">1. Acceptance of Terms</h3>
            <p>
              By accessing and using this website, you accept and agree to be bound by the terms 
              and provision of this agreement.
            </p>

            <h3 className="terms-title">2. Use License</h3>
            <ul className="terms-ul">
              <li>Permission is granted to temporarily download one copy of the materials on our website for personal, non-commercial transitory viewing only</li>
              <li>This is the grant of a license, not a transfer of title</li>
              <li>Under this license you may not modify or copy the materials</li>
              <li>Use the materials for any commercial purpose or for any public display</li>
            </ul>

            <h3 className="terms-title">3. Disclaimer</h3>
            <p>
              The materials on our website are provided on an 'as is' basis. We make no warranties, 
              expressed or implied, and hereby disclaim and negate all other warranties including 
              without limitation, implied warranties or conditions of merchantability, fitness for 
              a particular purpose, or non-infringement of intellectual property or other violation of rights.
            </p>

            <h3 className="terms-title">4. Limitations</h3>
            <p>
              In no event shall LoanFlow or its suppliers be liable for any damages (including, 
              without limitation, damages for loss of data or profit, or due to business interruption) 
              arising out of the use or inability to use the materials on our website.
            </p>

            <h3 className="terms-title">5. Privacy Policy</h3>
            <p>
              Your privacy is important to us. Our Privacy Policy explains how we collect, use, 
              and protect your information when you use our service.
            </p>

            <h3 className="terms-title">6. Governing Law</h3>
            <p>
              These terms and conditions are governed by and construed in accordance with the laws 
              and you irrevocably submit to the exclusive jurisdiction of the courts in that state or location.
            </p>

            <h3 className="terms-title">7. Changes to Terms</h3>
            <p>
              We reserve the right to revise these terms and conditions at any time without notice. 
              By using this website, you agree to be bound by the current version of these terms and conditions.
            </p>

            <h3 className="terms-title">8. Contact Information</h3>
            <p>
              If you have any questions about these Terms and Conditions, please contact us at{' '}
              <a href="mailto:legal@loanflow.com">legal@loanflow.com</a>.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TermsConditions;