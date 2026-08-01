{
  conclusion: `
<p><strong>実装完了</strong> — デモ中バナー（案C 気配）＋ワンクリック復帰（元ページ・スクロール位置まで）を jess 本体に実装しました。ホストアプリは1行も書かずに全機能が有効になります（<code>jess.banner=false</code> で全停止・view publish で見た目差し替え可）。</p>
<ul>
<li>テスト 29件 全緑（新規9件: バナー注入・レコーダー・JSON/Livewire除外・exit の open redirect 防止・exit 後の実DB復帰）</li>
<li>前回⚠3点はすべて解消（本ページ E2 参照）</li>
<li>コミット済み: <code>d2b7c2e</code>（worktree ブランチ）</li>
</ul>
<p>各項目を判定し、下部の「提出」を押してください。</p>`,
  items: [
    {
      heading: "E1. 復帰ジャーニーが実装版CSSで通る（ライブで通し体験可）",
      note: `
<p><a href="assets/jess-banner/journey-origin.html" target="_blank"><strong>▶ ライブで通し体験</strong></a> — プロトタイプを実装と同一CSSに更新済み。§3 までスクロール → 進入 → 右上バナー「元に戻る」→ 同位置復帰＋トースト</p>
<img src="../assets/jess-banner/shot-j3b-restored.png" alt="復帰後（トースト表示）">`,
    },
    {
      heading: "E2. 前回⚠3点の解消: 長いレシピでも「元に戻る」が常に見え、収納時は点だけ",
      note: `
<p><strong>展開時</strong> — 3 fragment の長いレシピでも exit リンクは右端に常時表示（レシピ側が ellipsis）。前回はここが刈られて戻れなかった＝トースト不発の根因</p>
<img src="../assets/jess-banner/crop-k1-expanded.png" alt="展開時クローズアップ">
<p><strong>収納時</strong> — 文字は opacity で消え、点のみ（左端の覗きなし）</p>
<img src="../assets/jess-banner/crop-k2-collapsed.png" alt="収納時クローズアップ">`,
    },
    {
      heading: "E3. 正直な注記 — 未検証で残っているもの",
      note: `
<ul>
<li>△ 実ホストアプリ（nadi / nonblok）での動作は未検証 — リリース後の composer update 適用時に <code>/demo/multi-area</code> で最終確認</li>
<li>△ 厳格CSPホストでは inline style/script が弾かれバナーが出ない（実害なしだが未実測）</li>
<li>✅ その他はテストで担保: 注入条件の絞り込み・open redirect 防止・cookie 削除による実DB復帰</li>
</ul>`,
    },
  ],
  gate: [
    "承認 — main マージ＆リリースへ | 先の2修正と合わせて tag/push（akihiko-takai 切替）まで進める",
    "修正 | ⚠コメントを反映して再提示",
    "保留 | コミットのままにしておく",
  ],
}
