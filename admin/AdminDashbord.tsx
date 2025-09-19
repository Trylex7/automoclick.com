import React, { useMemo, useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Line, Bar, Doughnut } from "react-chartjs-2";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";
import { Search, Users, Wrench, Settings, CalendarDays, Wallet, MessageSquare, Mail, Sparkles, Shield, Building2, ChevronDown, ChevronRight, MoreHorizontal, Plus, Download, Upload, Filter } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
);

/**
 * Automoclick Admin Dashboard
 * - Tailwind + shadcn/ui + Framer Motion
 * - Chart.js via react-chartjs-2 (Line, Bar, Doughnut)
 * - Sections: KPI, CA charts (jour/sem/trimestre/année), Clients/Pros, Newsletters, Chats, RDVs, Prestations, Admins & Maintenance
 * - This is a front-end scaffold ready to wire to your PHP APIs.
 */

const theme = {
  brand: {
    // Subtle automotive feel: slate + amber accents
    bg: "bg-slate-50",
    card: "bg-white",
    text: "text-slate-900",
    subtext: "text-slate-500",
    accent: "text-amber-500",
    ring: "ring-amber-400/40",
    gradient: "bg-gradient-to-r from-amber-500 to-orange-500",
  },
};

// --- Mock helpers (replace with live data from your API) ---
const now = new Date();
const formatCurrency = (n) => new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(n);

function genSeries(days = 30, max = 2000) {
  return Array.from({ length: days }, (_, i) => ({
    x: new Date(now.getFullYear(), now.getMonth(), i + 1).toLocaleDateString("fr-FR"),
    y: Math.round(Math.random() * max),
  }));
}

const mockData = {
  caDaily: genSeries(30, 2500),
  caWeekly: Array.from({ length: 12 }, (_, i) => ({ x: `S${i + 1}`, y: Math.round(Math.random() * 12000 + 2500) })),
  caQuarterly: ["T1", "T2", "T3", "T4"].map((t) => ({ x: t, y: Math.round(Math.random() * 80000 + 20000) })),
  caYearly: Array.from({ length: 5 }, (_, i) => ({ x: `${new Date().getFullYear() - 4 + i}`, y: Math.round(Math.random() * 500000 + 120000) })),
  clientsByMonth: Array.from({ length: 12 }, (_, i) => ({ x: new Date(0, i).toLocaleString("fr-FR", { month: "short" }), y: Math.round(Math.random() * 400 + 50) })),
  prosByMonth: Array.from({ length: 12 }, (_, i) => ({ x: new Date(0, i).toLocaleString("fr-FR", { month: "short" }), y: Math.round(Math.random() * 120 + 10) })),
  kpis: {
    caThisMonth: 84230,
    caThisWeek: 21540,
    clients: 12890,
    pros: 1630,
    rdvs: 324,
  },
  // Management tables (short, demo only)
  clients: Array.from({ length: 8 }, (_, i) => ({ id: 1000 + i, name: `Client ${i + 1}`, email: `client${i + 1}@mail.com`, createdAt: "2025-07-" + String(10 + i).padStart(2, "0"), active: Math.random() > 0.2 })),
  pros: Array.from({ length: 8 }, (_, i) => ({ id: 2000 + i, siret: `123 456 78${i} 000${i}`, name: `Pro ${i + 1}`, category: ["Mécanique", "Carrosserie", "Pneus", "Vitrage"][i % 4], taux_horaire: 60 + i * 5, active: Math.random() > 0.1 })),
  newsletters: Array.from({ length: 6 }, (_, i) => ({ id: 3000 + i, email: `user${i}@example.com`, subscribedAt: "2025-06-" + String(5 + i).padStart(2, "0"), confirmed: Math.random() > 0.3 })),
  chats: Array.from({ length: 5 }, (_, i) => ({ id: 4000 + i, client: `Client ${i + 1}`, pro: `Pro ${i + 1}`, lastMsg: "Bonjour, j'ai une question sur le devis.", updatedAt: "2025-07-" + String(12 + i).padStart(2, "0"), open: Math.random() > 0.5 })),
  rdvs: Array.from({ length: 8 }, (_, i) => ({ id: 5000 + i, client: `Client ${i + 1}`, pro: `Pro ${i + 1}`, date: `2025-08-${String(10 + i).padStart(2, "0")}`, prix: Math.round(Math.random() * 500 + 50), status: ["Confirmé", "En attente", "Annulé"][i % 3] })),
  prestations: Array.from({ length: 8 }, (_, i) => ({ id: 6000 + i, pro: `Pro ${i + 1}`, nom: ["Vidange", "Freins", "Carrosserie", "Pneumatiques"][i % 4], duree: [30, 60, 90, 120][i % 4], prix: Math.round(Math.random() * 250 + 50), stock: 10 + i })),
  admins: [
    { id: 1, name: "Yann (Owner)", role: "Super Admin", email: "yann@nfytech.fr", twoFA: true, active: true },
    { id: 2, name: "Opérateur", role: "Support", email: "support@automoclick.com", twoFA: false, active: true },
  ],
  maintenance: { safeMode: false, version: "v1.6.2", lastDeploy: "2025-08-20 14:12" },
};

function SectionHeader({ icon: Icon, title, subtitle, actions }) {
  return (
    <div className="flex items-center justify-between mb-4">
      <div className="flex items-center gap-3">
        <div className={`p-2 rounded-2xl ${theme.brand.gradient} text-white shadow`}> <Icon size={18} /> </div>
        <div>
          <h3 className="text-lg font-semibold leading-tight">{title}</h3>
          {subtitle && <p className="text-xs {theme.brand.subtext}">{subtitle}</p>}
        </div>
      </div>
      <div className="flex items-center gap-2">{actions}</div>
    </div>
  );
}

function KPICard({ icon: Icon, label, value, hint }) {
  return (
    <Card className="rounded-2xl shadow-sm">
      <CardContent className="p-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-slate-500">{label}</p>
            <p className="text-2xl font-semibold mt-1">{value}</p>
            {hint && <p className="text-xs text-slate-400 mt-1">{hint}</p>}
          </div>
          <div className={`p-3 rounded-2xl ${theme.brand.gradient} text-white shadow-md`}>
            <Icon size={20} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function SimpleTable({ columns, data, onAction }) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50">
          <tr>
            {columns.map((c) => (
              <th key={c.accessor} className="text-left px-4 py-3 font-medium text-slate-600">{c.header}</th>
            ))}
            <th className="px-2"></th>
          </tr>
        </thead>
        <tbody>
          {data.map((row) => (
            <tr key={row.id} className="odd:bg-white even:bg-slate-50/50">
              {columns.map((c) => (
                <td key={c.accessor} className="px-4 py-3 whitespace-nowrap">
                  {c.cell ? c.cell(row) : row[c.accessor]}
                </td>
              ))}
              <td className="px-2 text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon"><MoreHorizontal size={18} /></Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuItem onClick={() => onAction?.("edit", row)}>Modifier</DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onAction?.("disable", row)}>Désactiver</DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onAction?.("delete", row)} className="text-red-600">Supprimer</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ChartCard({ title, subtitle, children, right }) {
  return (
    <Card className="rounded-2xl shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">{title}</CardTitle>
          <div>{right}</div>
        </div>
        {subtitle && <p className="text-xs text-slate-400 mt-1">{subtitle}</p>}
      </CardHeader>
      <CardContent className="pt-0">{children}</CardContent>
    </Card>
  );
}

const periodOptions = [
  { key: "daily", label: "Journalier" },
  { key: "weekly", label: "Hebdo" },
  { key: "quarterly", label: "Trimestriel" },
  { key: "yearly", label: "Annuel" },
];

function useCAChart(period) {
  const series = useMemo(() => {
    switch (period) {
      case "daily": return mockData.caDaily;
      case "weekly": return mockData.caWeekly;
      case "quarterly": return mockData.caQuarterly;
      case "yearly": return mockData.caYearly;
      default: return mockData.caDaily;
    }
  }, [period]);

  const data = useMemo(() => ({
    labels: series.map((d) => d.x),
    datasets: [
      {
        label: "CA (EUR)",
        data: series.map((d) => d.y),
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.35,
      },
    ],
  }), [series]);

  const options = useMemo(() => ({
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { mode: "index", intersect: false },
    },
    scales: {
      y: { ticks: { callback: (v) => formatCurrency(v) } },
      x: { grid: { display: false } },
    },
  }), []);

  return { data, options };
}

function ClientsProsChart() {
  const data = useMemo(() => ({
    labels: mockData.clientsByMonth.map((d) => d.x),
    datasets: [
      { label: "Clients", data: mockData.clientsByMonth.map((d) => d.y) },
      { label: "Pros", data: mockData.prosByMonth.map((d) => d.y) },
    ],
  }), []);

  const options = useMemo(() => ({
    responsive: true,
    plugins: { legend: { position: "top" } },
    scales: { x: { grid: { display: false } } },
  }), []);

  return <Bar data={data} options={options} height={120} />;
}

function CACategoryDonut() {
  const data = useMemo(() => ({
    labels: ["Prestation", "Pièces", "Main d'œuvre", "Autres"],
    datasets: [{ data: [45, 25, 20, 10] }],
  }), []);
  return <Doughnut data={data} />;
}

export default function AdminDashboard() {
  const [period, setPeriod] = useState("daily");
  const { data: caData, options: caOptions } = useCAChart(period);
  const [query, setQuery] = useState("");
  const [activeTab, setActiveTab] = useState("overview");

  const filtered = {
    clients: mockData.clients.filter((c) => `${c.name} ${c.email}`.toLowerCase().includes(query.toLowerCase())),
    pros: mockData.pros.filter((p) => `${p.name} ${p.siret}`.toLowerCase().includes(query.toLowerCase())),
    newsletters: mockData.newsletters.filter((n) => n.email.toLowerCase().includes(query.toLowerCase())),
    chats: mockData.chats.filter((c) => `${c.client} ${c.pro}`.toLowerCase().includes(query.toLowerCase())),
    rdvs: mockData.rdvs.filter((r) => `${r.client} ${r.pro}`.toLowerCase().includes(query.toLowerCase())),
    prestations: mockData.prestations.filter((p) => `${p.pro} ${p.nom}`.toLowerCase().includes(query.toLowerCase())),
    admins: mockData.admins.filter((a) => `${a.name} ${a.email}`.toLowerCase().includes(query.toLowerCase())),
  };

  // Demo: export mock tables as CSV
  const exportCSV = (rows, name = "export") => {
    const csv = [Object.keys(rows[0]).join(",")].concat(rows.map((r) => Object.values(r).map((v) => `"${String(v).replaceAll('"', '""')}"`).join(","))).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = `${name}.csv`; a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className={`min-h-screen ${theme.brand.bg} ${theme.brand.text}`}>
      {/* Topbar */}
      <div className="sticky top-0 z-30 backdrop-blur supports-[backdrop-filter]:bg-white/70 bg-white/90 border-b">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center gap-3">
          <div className="flex items-center gap-2">
            <div className={`w-9 h-9 rounded-2xl ${theme.brand.gradient} grid place-items-center text-white shadow-md`}>
              <Wrench size={18} />
            </div>
            <div>
              <p className="text-xs text-slate-500">Automoclick</p>
              <h1 className="text-base font-semibold leading-none">Admin Dashboard</h1>
            </div>
          </div>

          <div className="ml-auto flex items-center gap-2 w-full sm:w-auto">
            <div className="relative w-full sm:w-80">
              <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Recherche globale (clients, pros, rdvs…)" className="pl-9 rounded-2xl" />
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            </div>
            <Button variant="outline" className="rounded-2xl"><Filter size={16} className="mr-2"/>Filtres</Button>
            <Button className={`rounded-2xl ${theme.brand.gradient}`}> <Sparkles size={16} className="mr-2"/> Actions rapides</Button>
          </div>
        </div>
      </div>

      {/* Body */}
      <div className="max-w-7xl mx-auto p-4 grid grid-cols-1 lg:grid-cols-12 gap-4">
        {/* Sidebar */}
        <aside className="lg:col-span-3 xl:col-span-2">
          <Card className="rounded-2xl sticky top-20">
            <CardContent className="p-3">
              <nav className="flex flex-col">
                {[
                  { key: "overview", label: "Vue d'ensemble", icon: Settings },
                  { key: "clients", label: "Clients", icon: Users },
                  { key: "pros", label: "Professionnels", icon: Building2 },
                  { key: "newsletters", label: "Newsletters", icon: Mail },
                  { key: "chats", label: "Chats", icon: MessageSquare },
                  { key: "rdvs", label: "Rendez-vous", icon: CalendarDays },
                  { key: "prestations", label: "Prestations", icon: Wallet },
                  { key: "admins", label: "Admins", icon: Shield },
                  { key: "maintenance", label: "Maintenance", icon: Wrench },
                ].map(({ key, label, icon: Icon }) => (
                  <button
                    key={key}
                    onClick={() => setActiveTab(key)}
                    className={`flex items-center justify-between px-3 py-2 rounded-xl text-left hover:bg-slate-50 ${activeTab === key ? "bg-slate-100" : ""}`}
                  >
                    <span className="flex items-center gap-2"><Icon size={16}/> {label}</span>
                    {activeTab === key ? <ChevronDown size={16}/> : <ChevronRight size={16}/>}
                  </button>
                ))}
              </nav>
            </CardContent>
          </Card>
        </aside>

        {/* Main */}
        <main className="lg:col-span-9 xl:col-span-10 space-y-4">
          {/* Overview */}
          {activeTab === "overview" && (
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <KPICard icon={Wallet} label="CA (mois)" value={formatCurrency(mockData.kpis.caThisMonth)} hint={"Cette semaine: " + formatCurrency(mockData.kpis.caThisWeek)} />
                <KPICard icon={Users} label="Total Clients" value={mockData.kpis.clients.toLocaleString("fr-FR")} />
                <KPICard icon={Building2} label="Pros actifs" value={mockData.kpis.pros.toLocaleString("fr-FR")} />
                <KPICard icon={CalendarDays} label="RDVs (30j)" value={mockData.kpis.rdvs} />
              </div>

              <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <div className="xl:col-span-2">
                  <ChartCard title="Chiffre d'affaires" subtitle="Suivi multi-périodes" right={
                    <div className="flex gap-2">
                      {periodOptions.map((p) => (
                        <Button key={p.key} size="sm" variant={period === p.key ? "default" : "outline"} className={`rounded-xl ${period === p.key ? theme.brand.gradient + " text-white" : ""}`} onClick={() => setPeriod(p.key)}>
                          {p.label}
                        </Button>
                      ))}
                    </div>
                  }>
                    <div className="h-64">
                      <Line data={caData} options={caOptions} />
                    </div>
                  </ChartCard>
                </div>

                <div className="space-y-4">
                  <ChartCard title="Répartition CA" subtitle="Catégories">
                    <div className="h-64 grid place-items-center">
                      <CACategoryDonut />
                    </div>
                  </ChartCard>
                  <ChartCard title="Nouveaux comptes" subtitle="Clients vs Pros par mois">
                    <div className="h-64">
                      <ClientsProsChart />
                    </div>
                  </ChartCard>
                </div>
              </div>
            </div>
          )}

          {/* Clients */}
          {activeTab === "clients" && (
            <div className="space-y-4">
              <SectionHeader icon={Users} title="Gestion des clients" subtitle="Créer, éditer, activer/désactiver" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.clients, "clients")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Nouveau client</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Nom", accessor: "name" },
                  { header: "Email", accessor: "email" },
                  { header: "Créé le", accessor: "createdAt" },
                  { header: "Actif", accessor: "active", cell: (r) => <Badge variant={r.active ? "default" : "secondary"}>{r.active ? "Oui" : "Non"}</Badge> },
                ]}
                data={filtered.clients}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Pros */}
          {activeTab === "pros" && (
            <div className="space-y-4">
              <SectionHeader icon={Building2} title="Gestion des professionnels" subtitle="SIRET, catégories, taux horaires, statut" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.pros, "professionnels")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Nouveau pro</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "SIRET", accessor: "siret" },
                  { header: "Nom", accessor: "name" },
                  { header: "Catégorie", accessor: "category" },
                  { header: "Taux horaire", accessor: "taux_horaire", cell: (r) => formatCurrency(r.taux_horaire) + "/h" },
                  { header: "Actif", accessor: "active", cell: (r) => <Badge variant={r.active ? "default" : "secondary"}>{r.active ? "Oui" : "Non"}</Badge> },
                ]}
                data={filtered.pros}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Newsletters */}
          {activeTab === "newsletters" && (
            <div className="space-y-4">
              <SectionHeader icon={Mail} title="Abonnés Newsletters" subtitle="Gestion des abonnements" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.newsletters, "newsletters")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Ajouter email</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Email", accessor: "email" },
                  { header: "Inscription", accessor: "subscribedAt" },
                  { header: "Confirmé", accessor: "confirmed", cell: (r) => <Badge variant={r.confirmed ? "default" : "secondary"}>{r.confirmed ? "Oui" : "Non"}</Badge> },
                ]}
                data={filtered.newsletters}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Chats */}
          {activeTab === "chats" && (
            <div className="space-y-4">
              <SectionHeader icon={MessageSquare} title="Chats" subtitle="Conversations client ↔ pro" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.chats, "chats")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Client", accessor: "client" },
                  { header: "Pro", accessor: "pro" },
                  { header: "Dernier message", accessor: "lastMsg" },
                  { header: "MAJ", accessor: "updatedAt" },
                  { header: "Ouvert", accessor: "open", cell: (r) => <Badge variant={r.open ? "default" : "secondary"}>{r.open ? "Oui" : "Non"}</Badge> },
                ]}
                data={filtered.chats}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* RDVs */}
          {activeTab === "rdvs" && (
            <div className="space-y-4">
              <SectionHeader icon={CalendarDays} title="Rendez-vous" subtitle="Liste des RDVs et prix" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.rdvs, "rdvs")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Nouveau RDV</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Client", accessor: "client" },
                  { header: "Pro", accessor: "pro" },
                  { header: "Date", accessor: "date" },
                  { header: "Prix", accessor: "prix", cell: (r) => formatCurrency(r.prix) },
                  { header: "Statut", accessor: "status" },
                ]}
                data={filtered.rdvs}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Prestations */}
          {activeTab === "prestations" && (
            <div className="space-y-4">
              <SectionHeader icon={Wallet} title="Prestations des professionnels" subtitle="Catalogue & stocks" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.prestations, "prestations")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Nouvelle prestation</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Pro", accessor: "pro" },
                  { header: "Nom", accessor: "nom" },
                  { header: "Durée (min)", accessor: "duree" },
                  { header: "Prix", accessor: "prix", cell: (r) => formatCurrency(r.prix) },
                  { header: "Stock", accessor: "stock" },
                ]}
                data={filtered.prestations}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Admins */}
          {activeTab === "admins" && (
            <div className="space-y-4">
              <SectionHeader icon={Shield} title="Administrateurs" subtitle="Rôles, 2FA & statuts" actions={
                <>
                  <Button variant="outline" onClick={() => exportCSV(filtered.admins, "admins")} className="rounded-xl"><Download size={14} className="mr-2"/>Exporter</Button>
                  <Button className={`${theme.brand.gradient} rounded-xl`}><Plus size={14} className="mr-2"/>Nouvel admin</Button>
                </>
              } />
              <SimpleTable
                columns={[
                  { header: "ID", accessor: "id" },
                  { header: "Nom", accessor: "name" },
                  { header: "Rôle", accessor: "role" },
                  { header: "Email", accessor: "email" },
                  { header: "2FA", accessor: "twoFA", cell: (r) => <Badge variant={r.twoFA ? "default" : "secondary"}>{r.twoFA ? "Activé" : "Non"}</Badge> },
                  { header: "Actif", accessor: "active", cell: (r) => <Badge variant={r.active ? "default" : "secondary"}>{r.active ? "Oui" : "Non"}</Badge> },
                ]}
                data={filtered.admins}
                onAction={(type, row) => console.log(type, row)}
              />
            </div>
          )}

          {/* Maintenance */}
          {activeTab === "maintenance" && (
            <div className="space-y-4">
              <SectionHeader icon={Wrench} title="Maintenance" subtitle="Mode sécurisé, déploiements & tâches" actions={<></>} />
              <Card className="rounded-2xl">
                <CardContent className="p-6 space-y-6">
                  <div className="grid sm:grid-cols-2 gap-4">
                    <div>
                      <Label className="text-slate-500">Version actuelle</Label>
                      <div className="text-lg font-semibold mt-1">{mockData.maintenance.version}</div>
                    </div>
                    <div>
                      <Label className="text-slate-500">Dernier déploiement</Label>
                      <div className="text-lg font-semibold mt-1">{mockData.maintenance.lastDeploy}</div>
                    </div>
                  </div>

                  <div className="flex items-center justify-between rounded-2xl border p-4">
                    <div>
                      <p className="font-medium">Mode maintenance (Safe Mode)</p>
                      <p className="text-sm text-slate-500">Désactive les paiements et les réservations pour tous les utilisateurs.</p>
                    </div>
                    <Switch checked={mockData.maintenance.safeMode} onCheckedChange={(v) => console.log("toggle maintenance", v)} />
                  </div>

                  <div className="flex flex-wrap gap-2">
                    <Button variant="outline" className="rounded-xl"><Upload size={16} className="mr-2"/>Déployer</Button>
                    <Button variant="outline" className="rounded-xl">Redémarrage intelligent</Button>
                    <Button className={`${theme.brand.gradient} rounded-xl`}>Purger cache</Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          )}
        </main>
      </div>

      {/* Footer */}
      <div className="max-w-7xl mx-auto px-4 py-6 text-xs text-slate-400">
        © {new Date().getFullYear()} Automoclick — Dashboard admin
      </div>
    </div>
  );
}
