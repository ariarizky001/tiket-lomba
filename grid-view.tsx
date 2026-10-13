'use client'

import { useState, useEffect } from 'react'
import { quickInputSlot, updateSlotName, markSlotPaid, cancelSlot } from './actions'
import { createClient } from '@/lib/supabase/client'
import type { Database } from '@/types/database'

type Session = Database['public']['Tables']['sessions']['Row']
type Slot = Database['public']['Tables']['slots']['Row']
type AntreanSlot = Database['public']['Views']['v_antrean']['Row']
type DisplaySlot = Slot | AntreanSlot

function formatRupiah(amount: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount)
}

export function GridView({ sessions, eventId }: { sessions: Session[]; eventId: number }) {
  const [sessionSlots, setSessionSlots] = useState<Record<number, Slot[]>>({})
  const [sessionAntrean, setSessionAntrean] = useState<Record<number, AntreanSlot[]>>({})
  const [showAntrean, setShowAntrean] = useState<Record<number, boolean>>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [selectedSlot, setSelectedSlot] = useState<DisplaySlot | null>(null)
  const [quickInputSessionId, setQuickInputSessionId] = useState<number | null>(null)
  const [dialogType, setDialogType] = useState<'quick' | 'actions' | null>(null)
  const [actionPending, setActionPending] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  const refreshSlots = () => setRefreshKey(prev => prev + 1)

  useEffect(() => {
    const fetchSlots = async () => {
      if (sessions.length === 0) return

      setLoading(true)
      setError(null)

      const supabase = createClient()

      try {
        const sessionIds = sessions.map(s => s.id)

        const { data: slotsData, error: slotsError } = await supabase
          .from('slots')
          .select('*')
          .in('session_id', sessionIds)
          .order('nomor_gantangan', { ascending: true })

        if (slotsError) throw slotsError

        const { data: antreanData, error: antreanError } = await supabase
          .from('v_antrean')
          .select('*')
          .in('session_id', sessionIds)
          .order('urutan', { ascending: true })

        if (antreanError) throw antreanError

        const slotsBySession: Record<number, Slot[]> = {}
        const antreanBySession: Record<number, AntreanSlot[]> = {}

        sessions.forEach(session => {
          slotsBySession[session.id] = slotsData?.filter(s => s.session_id === session.id) || []
          antreanBySession[session.id] = antreanData?.filter(s => s.session_id === session.id) || []
        })

        setSessionSlots(slotsBySession)
        setSessionAntrean(antreanBySession)
      } catch (err) {
        setError('Gagal memuat data slot')
        console.error(err)
      } finally {
        setLoading(false)
      }
    }

    fetchSlots()

    const supabase = createClient()
    let debounceTimer: NodeJS.Timeout

    const channel = supabase
      .channel(`event-${eventId}`)
      .on(
        'postgres_changes',
        { event: '*', schema: 'public', table: 'slots' },
        () => {
          clearTimeout(debounceTimer)
          debounceTimer = setTimeout(() => {
            fetchSlots()
          }, 300)
        }
      )
      .subscribe()

    return () => {
      clearTimeout(debounceTimer)
      supabase.removeChannel(channel)
    }
  }, [sessions, refreshKey, eventId])

  const handleQuickInput = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    if (!quickInputSessionId) return

    setActionPending(true)
    setActionError(null)

    const formData = new FormData(e.currentTarget)
    const result = await quickInputSlot(quickInputSessionId, eventId, formData)

    setActionPending(false)

    if (result?.error) {
      setActionError(result.error)
    } else {
      setDialogType(null)
      setQuickInputSessionId(null)
      refreshSlots()
    }
  }

  const handleUpdateName = async () => {
    if (!selectedSlot || !selectedSlot.id) return

    const nama = prompt('Masukkan nama baru:', selectedSlot.nama_peserta || '')
    if (!nama) return

    setActionPending(true)
    setActionError(null)

    const result = await updateSlotName(selectedSlot.id, eventId, nama)

    setActionPending(false)

    if (result?.error) {
      setActionError(result.error)
    } else {
      setDialogType(null)
      setSelectedSlot(null)
      refreshSlots()
    }
  }

  const handleMarkPaid = async () => {
    if (!selectedSlot || !selectedSlot.id) return

    setActionPending(true)
    setActionError(null)

    const result = await markSlotPaid(selectedSlot.id, eventId, selectedSlot.invoice_id)

    setActionPending(false)

    if (result?.error) {
      setActionError(result.error)
    } else {
      setDialogType(null)
      setSelectedSlot(null)
      refreshSlots()
    }
  }

  const handleCancel = async () => {
    if (!selectedSlot || !selectedSlot.id) return

    if (!confirm('Yakin ingin membatalkan slot ini? Nama peserta akan dihapus.')) {
      return
    }

    setActionPending(true)
    setActionError(null)

    const result = await cancelSlot(selectedSlot.id, eventId)

    setActionPending(false)

    if (result?.error) {
      setActionError(result.error)
    } else {
      setDialogType(null)
      setSelectedSlot(null)
      refreshSlots()
    }
  }

  if (loading) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500 dark:text-gray-400">Memuat data slot...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-red-700 dark:text-red-400">
        {error}
      </div>
    )
  }

  if (sessions.length === 0) {
    return (
      <div className="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow">
        <p className="text-gray-500 dark:text-gray-400">
          Belum ada sesi. Silakan tambah sesi terlebih dahulu.
        </p>
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
      {sessions.map(session => {
        const slots = sessionSlots[session.id] || []
        const antrean = sessionAntrean[session.id] || []
        const useAntrean = showAntrean[session.id] || false
        const displaySlots: DisplaySlot[] = useAntrean ? antrean : slots

        return (
          <div key={session.id} className="bg-white dark:bg-gray-800 rounded-lg shadow p-3 h-full flex flex-col">
            <div className="flex justify-between items-start mb-2">
              <div className="min-w-0">
                <h2 className="font-bold text-gray-900 dark:text-white truncate text-sm">
                  {session.nama_sesi}
                </h2>
                <p className="text-xs text-gray-600 dark:text-gray-400 truncate">
                  {formatRupiah(session.harga_tiket)}
                </p>
              </div>
              <div className="flex gap-1 flex-shrink-0">
                <button
                  onClick={() => {
                    setQuickInputSessionId(session.id)
                    setActionError(null)
                    setDialogType('quick')
                  }}
                  className="px-2 py-0.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded transition-colors"
                >
                  + Tambah
                </button>
                <button
                  onClick={() =>
                    setShowAntrean(prev => ({ ...prev, [session.id]: !prev[session.id] }))
                  }
                  className="px-2 py-0.5 text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                  {useAntrean ? 'Urut Nomor' : 'Urut Antrean'}
                </button>
              </div>
            </div>

            <div className="space-y-0.5 flex-1 overflow-y-auto min-h-0">
              {displaySlots.map(slot => {
                const isKosong = !slot.nama_peserta
                const isLunas = slot.status_bayar === 'LUNAS'
                const textColor = isKosong
                  ? 'text-gray-400 dark:text-gray-500'
                  : isLunas
                  ? 'text-black dark:text-white'
                  : 'text-red-600 dark:text-red-400'

                return (
                  <button
                    key={slot.id}
                    onClick={() => {
                      if (!isKosong) {
                        setSelectedSlot(slot)
                        setActionError(null)
                        setDialogType('actions')
                      }
                    }}
                    className="w-full px-2 py-1 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left text-[12px]"
                  >
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-gray-700 dark:text-gray-300 min-w-[2rem] text-[12px]">
                        {slot.nomor_gantangan}.
                      </span>
                      <span className={`flex-1 ${textColor} font-medium text-[12px]`}>
                        {isKosong ? 'NP' : slot.nama_peserta}
                      </span>
                    </div>
                    {useAntrean && !isKosong && (
                      <div className="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                        Antrean: {'urutan' in slot ? (slot as AntreanSlot).urutan : '-'}
                      </div>
                    )}
                  </button>
                )
              })}
            </div>
          </div>
        )
      })}

      {dialogType === 'quick' && quickInputSessionId && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4">
              Tambah Peserta
            </h3>

            {actionError && (
              <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-red-700 dark:text-red-400 text-sm">
                {actionError}
              </div>
            )}

            <form onSubmit={handleQuickInput} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Nama Peserta <span className="text-red-500">*</span>
                </label>
                <input
                  name="nama_peserta"
                  type="text"
                  required
                  disabled={actionPending}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:opacity-50"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Status Bayar
                </label>
                <select
                  name="status_bayar"
                  defaultValue="BELUM_LUNAS"
                  disabled={actionPending}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:opacity-50"
                >
                  <option value="BELUM_LUNAS">Belum Lunas</option>
                  <option value="LUNAS">Lunas</option>
                </select>
              </div>

              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={actionPending}
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {actionPending ? 'Menyimpan...' : 'Simpan'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setDialogType(null)
                    setQuickInputSessionId(null)
                    setActionError(null)
                  }}
                  disabled={actionPending}
                  className="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {dialogType === 'actions' && selectedSlot && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Slot {selectedSlot.nomor_gantangan}
            </h3>
            <p className="text-gray-600 dark:text-gray-400 mb-4">
              {selectedSlot.nama_peserta} -{' '}
              <span
                className={
                  selectedSlot.status_bayar === 'LUNAS'
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-red-600 dark:text-red-400'
                }
              >
                {selectedSlot.status_bayar === 'LUNAS' ? 'Lunas' : 'Belum Lunas'}
              </span>
            </p>

            {actionError && (
              <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-red-700 dark:text-red-400 text-sm">
                {actionError}
              </div>
            )}

            <div className="space-y-2">
              <button
                onClick={handleUpdateName}
                disabled={actionPending}
                className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Ganti Nama
              </button>
              {selectedSlot.status_bayar === 'BELUM_LUNAS' && (
                <button
                  onClick={handleMarkPaid}
                  disabled={actionPending}
                  className="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Tandai Lunas
                </button>
              )}
              <button
                onClick={handleCancel}
                disabled={actionPending}
                className="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Batalkan Slot
              </button>
              <button
                onClick={() => {
                  setDialogType(null)
                  setSelectedSlot(null)
                  setActionError(null)
                }}
                disabled={actionPending}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
