{
  conclusion: `
<p><strong>再調整版</strong> — 前回の提出で <strong>RP1=案C 気配・RP3=自動収納＋ホバー再展開 は確定</strong>。今回は RP2 の要望「同一アプリ内から入った場合、元のページ・スクロール位置までそのまま戻る」を実機構で試作し、通しで動くジャーニーとして提示します。</p>
<ul>
<li><strong>機構</strong>: jess が非デモページに注入する極小スクリプトが「いま居る場所（URL＋スクロール位置）」を sessionStorage に記録し続ける。「元に戻る」→ <code>/jess/exit</code> が cookie を消して記録先へリダイレクト → 着地時にスクロール位置を復元。cookie の上書きなし・サーバー側の新しい状態管理なし</li>
<li><strong>フォールバック</strong>: 記録が無い進入経路（FEATURES.md・共有リンク等アプリ外から）は <code>jess.exit_redirect</code>（既定 '/'）へ</li>
<li><strong>タブの扱い</strong>: sessionStorage はタブ単位＝「同じタブで入って戻る」ジャーニーと一致。別タブで開いた場合はブラウザにより記録が引き継がれず、その時はフォールバック着地（実害なし）</li>
</ul>
<p>各項目を判定し、下部の「提出」を押してください。</p>`,
  items: [
    {
      heading: "RJ1. 復帰ジャーニー: 進入前の位置まで完全に戻れている（ライブで通し体験可）",
      note: `
<p><a href="assets/jess-banner/journey-origin.html" target="_blank"><strong>▶ ライブで通し体験</strong></a> — §3 までスクロール → 「この状態を試す」→ デモ画面右上のバナー（3秒で点に収納・ホバー展開）→「元に戻る」</p>
<p><strong>①進入前</strong>: 機能一覧を §3 までスクロールした状態（ここが復帰目標）</p>
<img src="../assets/jess-banner/shot-j1-origin-scrolled.png" alt="進入前">
<p><strong>②デモ中</strong>: 案C 気配バナー・デモDBの世界</p>
<img src="../assets/jess-banner/shot-j2-demo.png" alt="デモ中">
<p><strong>③復帰後</strong>: ①と同一ページ・同一スクロール位置（playwright 実測で y が一致）＋確認トースト</p>
<img src="../assets/jess-banner/shot-j3b-restored.png" alt="復帰後">`,
    },
    {
      heading: "RJ2. 注入範囲の拡大を許容するか: 記録スクリプトは「jess.enabled 環境の全ページ」に載る",
      note: `
<ul>
<li>スクロール位置は進入<strong>前</strong>のページでしか観測できないため、記録スクリプト（1KB未満・外部通信なし）はデモセッション外のページにも注入される</li>
<li>従来方針「デモセッション中のみ注入」からの唯一の逸脱。<code>JESS_ENABLED=true</code> の環境（local/staging/demo）限定で、production には一切載らない</li>
<li>嫌なら <code>jess.banner=false</code> で記録ごと全停止（その場合の exit は常に固定着地先）</li>
</ul>`,
    },
  ],
  gate: [
    "この仕様で実装開始 | 確定3点＋復帰ジャーニーを jess に実装する",
    "再調整 | コメントに沿ってもう一度直す",
    "保留 | 先の2修正のマージ/リリースだけ先に進める",
  ],
}
