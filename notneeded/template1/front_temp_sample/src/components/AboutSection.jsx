import { useState } from 'react';

const AboutSection = () => {
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    email: ''
  });

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // Handle form submission here
    console.log('Form submitted:', formData);
    // Reset form
    setFormData({ name: '', phone: '', email: '' });
  };

  return (
    <div className="service-bg mt-100" id="about">
      <div className="container">
        <div className="row justify-content-between align-items-center mobile-row">
          <div className="col-lg-4 col-md-6" data-aos="fade-up-right">
            <h3 className="works-text">About us</h3>
            <img src="/images/about-us.png" alt="About us" className="img-box mt-3" />
            <p className="works-subtext mt-3">
              LoanFlow- Your trusted financial partner for loans. Quick approvals, 
              competitive rates, and personalized solutions to meet your unique needs. 
              Empowering you to achieve your financial goals. Apply online today!
            </p>
          </div>
          <div className="col-lg-4 col-md-6" data-aos="fade-up-left" id="contact">
            <div className="about-box">
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="name" className="form-label">Your Name</label>
                  <input 
                    type="text" 
                    id="name"
                    name="name"
                    className="form-control"
                    value={formData.name}
                    onChange={handleInputChange}
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="phone" className="form-label">Phone number</label>
                  <input 
                    type="tel" 
                    id="phone"
                    name="phone"
                    className="form-control"
                    value={formData.phone}
                    onChange={handleInputChange}
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="email" className="form-label">Email address</label>
                  <input 
                    type="email" 
                    id="email"
                    name="email"
                    className="form-control"
                    value={formData.email}
                    onChange={handleInputChange}
                    required
                  />
                </div>
                <div className="text-center">
                  <button type="submit" className="about-btn">SEND</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AboutSection;