import React, { useEffect } from 'react';

const FAQ = () => {
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
    <div className="wrapper faq-page" id="faq">
      <div className="main mt90">
        <h1 className="service-title">FAQ</h1>
        <div className="mt-50">
          <div className="container">
            <div className="accordion" id="accordionExample">
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingOne">
                  <button 
                    className="accordion-button" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseOne" 
                    aria-expanded="true" 
                    aria-controls="collapseOne"
                  >
                    What types of loans do you offer?
                  </button>
                </h2>
                <div 
                  id="collapseOne" 
                  className="accordion-collapse collapse show" 
                  aria-labelledby="headingOne" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      We offer a comprehensive range of loan products including personal loans, business loans, 
                      auto loans, and home improvement loans. Our loan amounts range from $1,000 to $50,000 
                      with flexible repayment terms from 6 to 60 months. Each loan is tailored to meet your 
                      specific financial needs and circumstances.
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingTwo">
                  <button 
                    className="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseTwo" 
                    aria-expanded="false" 
                    aria-controls="collapseTwo"
                  >
                    How fast can I get approved and receive funds?
                  </button>
                </h2>
                <div 
                  id="collapseTwo" 
                  className="accordion-collapse collapse" 
                  aria-labelledby="headingTwo" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      Our streamlined application process allows for quick decisions. Most applications 
                      receive a preliminary decision within 24 hours. Once approved, funds are typically 
                      deposited into your account within 1-3 business days. For urgent needs, we also 
                      offer same-day funding options for qualified applicants.
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingThree">
                  <button 
                    className="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseThree" 
                    aria-expanded="false" 
                    aria-controls="collapseThree"
                  >
                    What are the eligibility requirements?
                  </button>
                </h2>
                <div 
                  id="collapseThree" 
                  className="accordion-collapse collapse" 
                  aria-labelledby="headingThree" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      To qualify for a loan, you must be at least 18 years old, have a valid government-issued ID, 
                      proof of income, and an active bank account. We consider applicants with various credit 
                      backgrounds, and our flexible criteria mean that even those with less-than-perfect credit 
                      may still qualify for competitive rates.
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingFour">
                  <button 
                    className="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseFour" 
                    aria-expanded="false" 
                    aria-controls="collapseFour"
                  >
                    Are there any hidden fees or prepayment penalties?
                  </button>
                </h2>
                <div 
                  id="collapseFour" 
                  className="accordion-collapse collapse" 
                  aria-labelledby="headingFour" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      We believe in complete transparency. There are no hidden fees, and we clearly outline 
                      all costs upfront including any origination fees or processing charges. Additionally, 
                      there are no prepayment penalties, so you can pay off your loan early without any 
                      additional charges, potentially saving you money on interest.
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingFive">
                  <button 
                    className="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseFive" 
                    aria-expanded="false" 
                    aria-controls="collapseFive"
                  >
                    How secure is my personal and financial information?
                  </button>
                </h2>
                <div 
                  id="collapseFive" 
                  className="accordion-collapse collapse" 
                  aria-labelledby="headingFive" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      Your security is our top priority. We use bank-level 256-bit SSL encryption to protect 
                      all data transmission and store your information in secure, encrypted databases. We are 
                      fully compliant with industry security standards and never share your personal information 
                      with third parties without your explicit consent.
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="accordion-item">
                <h2 className="accordion-header" id="headingSix">
                  <button 
                    className="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseSix" 
                    aria-expanded="false" 
                    aria-controls="collapseSix"
                  >
                    What happens if I miss a payment?
                  </button>
                </h2>
                <div 
                  id="collapseSix" 
                  className="accordion-collapse collapse" 
                  aria-labelledby="headingSix" 
                  data-bs-parent="#accordionExample"
                >
                  <div className="accordion-body">
                    <p className="mb-0">
                      We understand that financial situations can change. If you miss a payment, we'll contact 
                      you immediately to discuss your options. We offer flexible solutions including payment 
                      deferrals, modified payment plans, and hardship programs. Our goal is to work with you 
                      to find a solution that gets you back on track without damaging your credit.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FAQ;