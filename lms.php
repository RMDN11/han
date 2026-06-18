<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pendidikan Profesional - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Glassmorphism & High-Contrast Styling */
        body {
            background-color: #0f172a; /* Slate 900 */
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at top right, #1e293b, #0f172a);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            border-radius: 1rem;
        }
        .smooth-transition { transition: all 0.3s ease; }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        // --- DUMMY DATA STATIS ---
        const DUMMY_COURSES = [
            { id: 1, title: "Leadership Pendidikan", mentor: "Dr. Budi Santoso", progress: 85, duration: "4 Jam", img: "https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=300&q=80" },
            { id: 2, title: "Administrasi Pendidikan", mentor: "Siti Aminah, M.Pd", progress: 40, duration: "3.5 Jam", img: "https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=300&q=80" },
            { id: 3, title: "Manajemen SDM Sekolah", mentor: "Ahmad Wijaya, Ph.D", progress: 10, duration: "5 Jam", img: "https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=300&q=80" },
            { id: 4, title: "Supervisi Akademik", mentor: "Dr. Rina Kusuma", progress: 0, duration: "6 Jam", img: "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=300&q=80" },
            { id: 5, title: "Evaluasi Pendidikan", mentor: "Dr. Budi Santoso", progress: 100, duration: "4.5 Jam", img: "https://images.unsplash.com/photo-1453728013993-6d66e9c9123a?auto=format&fit=crop&w=300&q=80" },
            { id: 6, title: "Perencanaan Pendidikan", mentor: "Siti Aminah, M.Pd", progress: 0, duration: "3 Jam", img: "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=300&q=80" },
            { id: 7, title: "Manajemen Keuangan Sekolah", mentor: "Ahmad Wijaya, Ph.D", progress: 0, duration: "4 Jam", img: "https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=300&q=80" },
            { id: 8, title: "Strategi Pembelajaran Modern", mentor: "Dr. Rina Kusuma", progress: 0, duration: "5.5 Jam", img: "https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=300&q=80" },
        ];

        // --- KOMPONEN LOGIN ---
        const Login = ({ onLogin }) => {
            const [email, setEmail] = useState('');
            const [password, setPassword] = useState('');
            const [error, setError] = useState('');

            const handleLogin = (e) => {
                e.preventDefault();
                if (email === 'peserta@demo.com' && password === '123456') {
                    onLogin({ role: 'peserta', name: 'Farhan (Peserta)', email });
                } else if (email === 'admin@demo.com' && password === '123456') {
                    onLogin({ role: 'admin', name: 'Administrator', email });
                } else {
                    setError('Email atau password salah! Gunakan akun demo.');
                }
            };

            return (
                <div className="flex items-center justify-center min-h-screen p-4">
                    <div className="glass-card p-8 w-full max-w-md">
                        <div className="text-center mb-8">
                            <h2 className="text-2xl font-bold text-white mb-2">Login LMS</h2>
                            <p className="text-slate-400 text-sm">Manajemen Pendidikan Profesional</p>
                        </div>
                        {error && <div className="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-sm">{error}</div>}
                        <form onSubmit={handleLogin} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-300 mb-1">Email</label>
                                <input type="email" value={email} onChange={e => setEmail(e.target.value)} required className="w-full bg-slate-800/50 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" placeholder="peserta@demo.com"/>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-300 mb-1">Password</label>
                                <input type="password" value={password} onChange={e => setPassword(e.target.value)} required className="w-full bg-slate-800/50 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" placeholder="123456"/>
                            </div>
                            <button type="submit" className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded smooth-transition">Masuk</button>
                        </form>
                        <div className="mt-6 text-sm text-slate-400 border-t border-slate-700 pt-4">
                            <p><strong>Akun Demo:</strong></p>
                            <p>Peserta: peserta@demo.com | 123456</p>
                            <p>Admin: admin@demo.com | 123456</p>
                        </div>
                    </div>
                </div>
            );
        };

        // --- DASHBOARD PESERTA ---
        const PesertaDashboard = ({ user, onLogout }) => {
            return (
                <div className="min-h-screen flex flex-col md:flex-row">
                    {/* Sidebar */}
                    <div className="w-full md:w-64 glass-card m-4 p-6 hidden md:block">
                        <h2 className="text-xl font-bold mb-8 text-blue-400"><i className="fas fa-graduation-cap mr-2"></i>LMS PRO</h2>
                        <ul className="space-y-4">
                            <li className="text-blue-400 cursor-pointer"><i className="fas fa-home mr-3"></i>Dashboard</li>
                            <li className="text-slate-400 hover:text-white cursor-pointer"><i className="fas fa-book mr-3"></i>Kelas Saya</li>
                            <li className="text-slate-400 hover:text-white cursor-pointer"><i className="fas fa-certificate mr-3"></i>Sertifikat</li>
                            <li className="text-slate-400 hover:text-white cursor-pointer"><i className="fas fa-user mr-3"></i>Profil</li>
                        </ul>
                    </div>

                    {/* Main Content */}
                    <div className="flex-1 p-4 md:p-8 overflow-y-auto">
                        <div className="flex justify-between items-center mb-8">
                            <h1 className="text-2xl font-bold">Welcome back, {user.name}</h1>
                            <button onClick={onLogout} className="bg-red-500/20 text-red-400 px-4 py-2 rounded hover:bg-red-500/40 smooth-transition"><i className="fas fa-sign-out-alt mr-2"></i>Logout</button>
                        </div>

                        {/* Banner & Pengumuman */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                            <div className="lg:col-span-2 glass-card p-6 bg-gradient-to-r from-blue-900/50 to-slate-900/50">
                                <h3 className="text-xl font-bold mb-2">Persiapan KBM Batch April 2026</h3>
                                <p className="text-slate-300 mb-4">Pastikan Anda telah menyelesaikan modul prasyarat sebelum sesi *onboarding* dimulai. Kelas akan dibuka penuh pada 13 April 2026.</p>
                                <button className="bg-blue-600 px-4 py-2 rounded text-sm hover:bg-blue-500 smooth-transition">Lihat Silabus</button>
                            </div>
                            <div className="glass-card p-6 border-l-4 border-l-yellow-500">
                                <h3 className="text-lg font-bold mb-2">Jadwal Terdekat</h3>
                                <p className="text-slate-300 text-sm mb-1"><i className="far fa-calendar-alt mr-2 text-yellow-500"></i>Ujian Muroja'ah Bulanan</p>
                                <p className="text-slate-400 text-xs ml-6 mb-3">3 - 8 April 2026</p>
                                <p className="text-slate-300 text-sm mb-1"><i className="fas fa-video mr-2 text-blue-400"></i>Live Session: Evaluasi</p>
                                <p className="text-slate-400 text-xs ml-6">10 April 2026, 19:00 WIB</p>
                            </div>
                        </div>

                        {/* Kelas Saya Grid */}
                        <h2 className="text-xl font-bold mb-4">Kelas Saya</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {DUMMY_COURSES.map(course => (
                                <div key={course.id} className="glass-card overflow-hidden hover:transform hover:-translate-y-1 smooth-transition">
                                    <img src={course.img} alt={course.title} className="w-full h-32 object-cover" />
                                    <div className="p-4">
                                        <h4 className="font-bold mb-1 truncate">{course.title}</h4>
                                        <p className="text-xs text-slate-400 mb-3"><i className="fas fa-chalkboard-teacher mr-1"></i>{course.mentor}</p>
                                        
                                        <div className="w-full bg-slate-700 rounded-full h-2 mb-2">
                                            <div className="bg-blue-500 h-2 rounded-full" style={{ width: `${course.progress}%` }}></div>
                                        </div>
                                        <div className="flex justify-between items-center text-xs text-slate-300 mb-4">
                                            <span>{course.progress}% Selesai</span>
                                            <span>{course.duration}</span>
                                        </div>
                                        
                                        <button className="w-full bg-slate-700 hover:bg-blue-600 text-white text-sm py-2 rounded smooth-transition">
                                            {course.progress === 0 ? 'Mulai Belajar' : course.progress === 100 ? 'Review' : 'Lanjutkan'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            );
        };

        // --- DASHBOARD ADMIN ---
        const AdminDashboard = ({ user, onLogout }) => {
            return (
                <div className="min-h-screen flex flex-col md:flex-row">
                    <div className="w-full md:w-64 glass-card m-4 p-6 hidden md:block">
                        <h2 className="text-xl font-bold mb-8 text-purple-400"><i className="fas fa-shield-alt mr-2"></i>ADMIN PANEL</h2>
                        <ul className="space-y-4">
                            <li className="text-purple-400 cursor-pointer"><i className="fas fa-chart-line mr-3"></i>Statistik</li>
                            <li className="text-slate-400 hover:text-white cursor-pointer"><i className="fas fa-users mr-3"></i>Peserta</li>
                            <li className="text-slate-400 hover:text-white cursor-pointer"><i className="fas fa-book-open mr-3"></i>Course</li>
                        </ul>
                    </div>

                    <div className="flex-1 p-4 md:p-8 overflow-y-auto">
                        <div className="flex justify-between items-center mb-8">
                            <h1 className="text-2xl font-bold">Admin Overview</h1>
                            <button onClick={onLogout} className="bg-red-500/20 text-red-400 px-4 py-2 rounded hover:bg-red-500/40 smooth-transition"><i className="fas fa-sign-out-alt mr-2"></i>Logout</button>
                        </div>

                        {/* Admin Stats */}
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                            <div className="glass-card p-4 border-t-4 border-t-purple-500">
                                <p className="text-slate-400 text-sm">Total Peserta</p>
                                <h2 className="text-3xl font-bold">2,450</h2>
                            </div>
                            <div className="glass-card p-4 border-t-4 border-t-blue-500">
                                <p className="text-slate-400 text-sm">Peserta Aktif</p>
                                <h2 className="text-3xl font-bold">1,890</h2>
                            </div>
                            <div className="glass-card p-4 border-t-4 border-t-green-500">
                                <p className="text-slate-400 text-sm">Total Course</p>
                                <h2 className="text-3xl font-bold">45</h2>
                            </div>
                            <div className="glass-card p-4 border-t-4 border-t-yellow-500">
                                <p className="text-slate-400 text-sm">Completion Rate</p>
                                <h2 className="text-3xl font-bold">68%</h2>
                            </div>
                        </div>

                        {/* Tabel Manajemen Peserta (Simulasi) */}
                        <div className="glass-card p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-bold">Manajemen Peserta Terbaru</h3>
                                <input type="text" placeholder="Cari peserta..." className="bg-slate-800 border border-slate-600 rounded px-3 py-1 text-sm text-white focus:outline-none"/>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-700 text-slate-400">
                                            <th className="py-3 px-4">Nama</th>
                                            <th className="py-3 px-4">Program</th>
                                            <th className="py-3 px-4">Progress</th>
                                            <th className="py-3 px-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr className="border-b border-slate-800 hover:bg-slate-800/50">
                                            <td className="py-3 px-4">Budi Darmawan</td>
                                            <td className="py-3 px-4">KBM Batch April 2026</td>
                                            <td className="py-3 px-4">45%</td>
                                            <td className="py-3 px-4"><span className="bg-green-500/20 text-green-400 px-2 py-1 rounded text-xs">Aktif</span></td>
                                        </tr>
                                        <tr className="border-b border-slate-800 hover:bg-slate-800/50">
                                            <td className="py-3 px-4">Anita Saraswati</td>
                                            <td className="py-3 px-4">Leadership Pendidikan</td>
                                            <td className="py-3 px-4">100%</td>
                                            <td className="py-3 px-4"><span className="bg-blue-500/20 text-blue-400 px-2 py-1 rounded text-xs">Lulus</span></td>
                                        </tr>
                                        <tr className="hover:bg-slate-800/50">
                                            <td className="py-3 px-4">Dedi Irawan</td>
                                            <td className="py-3 px-4">Manajemen Keuangan</td>
                                            <td className="py-3 px-4">10%</td>
                                            <td className="py-3 px-4"><span className="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded text-xs">Inaktif</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            );
        };

        // --- MAIN APP COMPONENT ---
        const App = () => {
            const [user, setUser] = useState(null);
            const [loading, setLoading] = useState(true);

            // Persistensi Data menggunakan localStorage sesuai instruksi
            useEffect(() => {
                const loggedInUser = localStorage.getItem('lms_demo_user');
                if (loggedInUser) {
                    setUser(JSON.parse(loggedInUser));
                }
                setLoading(false);
            }, []);

            const handleLogin = (userData) => {
                setUser(userData);
                localStorage.setItem('lms_demo_user', JSON.stringify(userData));
            };

            const handleLogout = () => {
                setUser(null);
                localStorage.removeItem('lms_demo_user');
            };

            if (loading) return <div className="min-h-screen flex items-center justify-center">Loading...</div>;

            if (!user) return <Login onLogin={handleLogin} />;

            return user.role === 'admin' 
                ? <AdminDashboard user={user} onLogout={handleLogout} /> 
                : <PesertaDashboard user={user} onLogout={handleLogout} />;
        };

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>