import React from "react";
import { Link, useNavigate } from "react-router-dom";
import "./Footer.css";

const Footer = () => {
  const navigate = useNavigate();

  const handleAnchorNavigation = (anchorId) => {
    // Navigate to home page first
    navigate('/');
    
    // Wait for navigation to complete, then scroll to the anchor
    setTimeout(() => {
      const element = document.getElementById(anchorId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    }, 100);
  };

  return (
    <footer className="footer-box" >
      <div className="container">
        <div className="row justify-content-between">
          <div className="col-lg-4">
            <h3 className="footer-title">Our Office</h3>
            <ul className="footer-ul-contact">
              <li>
                <a href="#">
                  <i className="bi bi-geo-alt-fill footer-contact-icons"></i>
                  700 Well St. #308,<br /> NV 89002
                </a>
              </li>
              <li>
                <a href="#">
                  <i className="bi bi-telephone-fill footer-contact-icons"></i>
                  1 888 489 8189
                </a>
              </li>
              <li>
                <a href="#">
                  <i className="bi bi-envelope-fill footer-contact-icons"></i>
                  cs@pulse.online
                </a>
              </li>
            </ul>
          </div>
          <div className="col-lg-4">
            <h3 className="footer-title">Quick Links</h3>
            <ul>
              <li>
                <button 
                  className="btn btn-link p-0 text-decoration-none"
                  onClick={() => handleAnchorNavigation('faq')}
                >
                  <i className="bi bi-chevron-right pe-2"></i>
                  FAQ's
                </button>
              </li>
              <li>
                <button 
                  className="btn btn-link p-0 text-decoration-none"
                  onClick={() => handleAnchorNavigation('about')}
                >
                  <i className="bi bi-chevron-right pe-2"></i>
                  About Us
                </button>
              </li>
              <li>
                <button 
                  className="btn btn-link p-0 text-decoration-none"
                  onClick={() => handleAnchorNavigation('contact')}
                >
                  <i className="bi bi-chevron-right pe-2"></i>
                  Contact Us
                </button>
              </li>
              <li>
                <Link to="/terms-conditions">
                  <i className="bi bi-chevron-right pe-2"></i>
                  Terms & Condition
                </Link>
              </li>
              <li>
                <Link to="/privacy-policy">
                  <i className="bi bi-chevron-right pe-2"></i>
                  Privacy
                </Link>
              </li>
            </ul>
          </div>
          <div className="col-lg-4">
            <h3 className="footer-title">Business Hours</h3>
            <div className="position-relative">
              <p className="workday-title">Monday - Friday</p>
              <p className="mb-0">9:00am - 05:00pm</p>
            </div>
            <div className="position-relative">
              <p className="workday-title">Saturday</p>
              <p className="mb-0">09:00am - 02:00pm</p>
            </div>
          </div>
        </div>
        
        {/* Footer Disclaimer */}
        <div className="row mt-4">
          <div className="col-12">
            <div className="footer-disclaimer">
              <hr className="mb-3" />
              <p className="disclaimer-text">
                <strong>DISCLAIMER</strong>: Loan APR ranges from 11% to 27% and repayment periods from 12 to 96 months. Example: a $5,500 unsecured loan, at 21% borrowed for 12 months, payments are $512.13 monthly. Total repayment including interest is $6,145.56.
              </p>
              <p className="disclaimer-text">
                LoanFlow, the operator of this website, does not broker loans to lenders and does not make installments or other loans or credit decisions. This website does not constitute an offer or solicitation of credit and will transmit the information you provide to the lender. Submitting your information to this website does not guarantee that credit will be approved. The operators of this website are not agents, representatives or intermediaries of any financial institution and do not endorse or charge for any services or products. Visitors to this website should also acknowledge that the operator of this website is not responsible for any disputes arising out of the conduct of lenders or applicants. Not all lenders can provide the credit they want, and not all borrowers are eligible. Transfer times may vary by lender and may also vary by individual financial institution. In certain circumstances, you may be required to provide faxes or other additional documentation, which may delay the approval process. This service is not available in all regions and the regions served by this website may change at any time without notice. Please contact the lender directly for any details, questions or concerns regarding your loan. Credit checks, consumer credit reports, and other personally identifiable information may be obtained from some of his Equifax and Trans Union. IMPORTANT INFORMATION ABOUT NEW ACCOUNT OPENING PROCEDURES to help the government combat terrorist financing and money laundering activities, federal law requires all financial institutions to obtain, and verify the identity of each individual who opens an account. What this means for you: When you open an account, lenders ask for your name, address, date of birth, and other information that can identify you. Lenders may also ask for a driver's license or other form of identification. Funds are generally deposited via direct deposit/certified check within 2-3 business days once approved.
              </p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;