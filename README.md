Student Result Management System (SRMS)
About the Project

The Student Result Management System (SRMS) is a web-based application designed to simplify the management of student academic records and examination results.

The system provides an efficient way to manage student information, subjects, classes, and results.

Features
Student Management
Subject Management
Class Management
Student Result Management
User Authentication
View Student Results
MySQL Database Integration
Technologies Used
Frontend: HTML, CSS, JavaScript
Backend: PHP
Database: MySQL
Containerization: Docker
Container Management: Docker Compose
Running the Project with Docker
Prerequisites

Make sure you have the following installed:

Docker
Docker Compose
Git
Clone the Repository
git clone https://github.com/lexy-gif/lex1.git
Navigate to the Project Directory
cd srms
Build and Start the Containers
docker compose up --build
Access the Application

Once the containers are running, open your browser and visit:

http://localhost:5000
Project Structure
srms/
│
├── Dockerfile
├── docker-compose.yml
├── src/
├── database/
└── README.md
Project Purpose

This project was developed to demonstrate practical skills in:

PHP web development
MySQL database management
Docker containerization
Docker Compose
Application deployment and DevOps practices
Author

Alex Mwangi

Networking | Cloud | DevOps
# Teacher academic relationships

See [Relational teacher management](docs/RELATIONAL_TEACHER_MANAGEMENT.md) for Dean workflows, student subject registration, migrations and validation commands.

# Dean academic workspace

After signing in, choose **Open Academic Workspace** on the Dean dashboard or **Academic Workspace** in the sidebar. The workspace is at [http://localhost:5000/dean-academics.php](http://localhost:5000/dean-academics.php). Teachers have a **My Academic Workspace** sidebar link.

See [CBE workspace usage and validation](docs/CBE_WORKSPACE.md) for the current flows, database prerequisite, checks and remaining integration work.

# Senior School

See [Senior School setup and usage](docs/SENIOR_SCHOOL.md) for Grades 10–12 pathways,
subject combinations, learner allocations, promotion, and teacher/student access.

# Application styling

See the [stylesheet guide](docs/CSS_GUIDE.md) for CSS file responsibilities, shared variables, and responsive checks.
