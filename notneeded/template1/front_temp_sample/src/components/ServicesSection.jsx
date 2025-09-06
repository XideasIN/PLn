const ServicesSection = () => {
  const services = [
    {
      image: "/images/service-img-1.png",
      title: "Personal loan",
      description: "Personal loans provide borrowers with flexibility in how they use the funds.",
      link: "#"
    },
    {
      image: "/images/service-img-2.png",
      title: "Business loan",
      description: "Business Loan Services provide financial assistance to businesses for various purposes.",
      link: "#"
    },
    {
      image: "/images/service-img-3.png",
      title: "Auto loan",
      description: "Auto Loan Services provide financing options for individuals businesses to purchase a vehicle.",
      link: "#"
    }
  ];

  return (
    <div className="service-bg mt-100" data-aos="fade-up" id="services">
      <div className="container">
        <h3 className="service-title mb-3">Our Services</h3>
        <div className="row">
          {services.map((service, index) => (
            <div key={index} className="col-lg-4 mt-3">
              <div className="service-box">
                <img src={service.image} alt={service.title} />
                <h4>{service.title}</h4>
                <p>{service.description}</p>
                <a href={service.link}>Apply now</a>
              </div>
            </div>
          ))}
        </div>
        <div className="text-center mt-4">
          <a href="#" className="view-btn">View more</a>
        </div>
      </div>
    </div>
  );
};

export default ServicesSection;