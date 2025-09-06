const HeroSection = () => {
  return (
    <div className="container">
      <div className="row justify-content-between align-items-center">
        <div className="col-lg-6 col-md-12" data-aos="fade-up-right">
          <h1 className="maintitle-text">Quick and Easy Loans for Your Financial Needs.</h1>
          <p className="subtitle-text">
            Our loan services offer a hassle-free and streamlined borrowing experience, 
            providing you with the funds you need in a timely manner to meet your financial requirements.
          </p>
          <a href="#services" className="getstart-btn">Get started</a>
        </div>
        <div className="col-lg-6 col-md-12" data-aos="fade-up-left">
          <img src="/images/hero-img.png" className="img-box" alt="Hero" />
        </div>
      </div>
    </div>
  );
};

export default HeroSection;