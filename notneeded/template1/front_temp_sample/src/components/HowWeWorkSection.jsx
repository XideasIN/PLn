const HowWeWorkSection = () => {
  const steps = [
    {
      number: "01",
      title: "Application",
      description: "The borrower submits a loan application to the bank, either in person, online, or through other channels. The application includes personal and financial information, such as income, employment history, credit score, and the purpose of the loan.",
      image: "/images/works-img-1.png",
      direction: "right"
    },
    {
      number: "02", 
      title: "Documentation and Verification",
      description: "The bank requests supporting documents from the borrower, such as identification proof, income statements, bank statements, and collateral details (if applicable). The bank verifies the information provided to assess the borrower's creditworthiness and eligibility for the loan.",
      image: "/images/works-img-2.png",
      direction: "left"
    },
    {
      number: "03",
      title: "Credit Assessment", 
      description: "The bank conducts a credit assessment to evaluate the borrower's creditworthiness and ability to repay the loan. This process involves analyzing the borrower's credit history, income stability, debt-to-income ratio, and other factors.",
      image: "/images/works-img-3.png",
      direction: "right"
    },
    {
      number: "04",
      title: "Loan Approval",
      description: "If the borrower meets the bank's lending criteria and passes the credit assessment, the loan is approved. The bank determines the loan amount, interest rate, repayment term, and any associated fees.",
      image: "/images/work-img-4.png", 
      direction: "left"
    }
  ];

  return (
    <div className="mt-100" id="how-we-work">
      <div className="container">
        <div className="service-title">How we works ?</div>
        <p className="works-subtext text-center">This is a process, how you can get loan for your self.</p>
        
        {steps.map((step, index) => (
          <div 
            key={index} 
            className={`row justify-content-around align-items-center mt-50 ${
              step.direction === 'right' ? 'mobile-column' : 'pad-left'
            }`}
          >
            {step.direction === 'right' ? (
              <>
                <div className="col-lg-5" data-aos="fade-up-right">
                  <img src={step.image} alt={step.title} className="img-box" />
                </div>
                <div className="col-lg-6 position-relative" data-aos="fade-up-left">
                  <h4 className="process-number">{step.number}</h4>
                  <h3 className="works-text">{step.title}</h3>
                  <p className="works-subtext">{step.description}</p>
                </div>
              </>
            ) : (
              <>
                <div className="col-lg-6 position-relative" data-aos="fade-up-right">
                  <h4 className="process-number">{step.number}</h4>
                  <h3 className="works-text">{step.title}</h3>
                  <p className="works-subtext">{step.description}</p>
                </div>
                <div className="col-lg-5" data-aos="fade-up-left">
                  <img src={step.image} alt={step.title} className="img-box" />
                </div>
              </>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default HowWeWorkSection;